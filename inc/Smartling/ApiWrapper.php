<?php

namespace Smartling;

use Psr\Log\LoggerInterface;
use Smartling\Exception\SmartlingDbException;
use Smartling\Exception\SmartlingFileDownloadException;
use Smartling\Exception\SmartlingFileUploadException;
use Smartling\Exception\SmartlingNetworkException;
use Smartling\SDK\FileUploadParameterBuilder;
use Smartling\SDK\SmartlingAPI;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;

/**
 * Class ApiWrapper
 *
 * @package Smartling
 */
class ApiWrapper implements ApiWrapperInterface {

	/**
	 * @var SettingsManager
	 */
	private $settings;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var SmartlingAPI
	 */
	protected $api;

	public function __construct ( SettingsManager $manager, LoggerInterface $logger ) {
		$this->settings = $manager;
		$this->logger   = $logger;
	}

	public function setApi ( ConfigurationProfileEntity $profile ) {
		$this->api = new SmartlingAPI(
			$profile->getApiUrl(),
			$profile->getApiKey(),
			$profile->getProjectId(),
			SmartlingAPI::PRODUCTION_MODE
		);
	}

	/**
	 * @param SubmissionEntity $submission
	 *
	 * @return ConfigurationProfileEntity
	 * @throws SmartlingDbException
	 */
	private function getConfigurationProfile ( SubmissionEntity $submission ) {
		$mainBlogId = $submission->getSourceBlogId();

		$possibleProfiles = $this->settings->findEntityByMainLocale( $mainBlogId );

		if ( 0 < count( $possibleProfiles ) ) {
			return reset( $possibleProfiles );
		}
		$message = vsprintf( 'No active profile found for main blog %s', array ( $mainBlogId ) );
		$this->logger->warning( $message );
		throw new SmartlingDbException( $message );
	}

	/**
	 * @param SubmissionEntity $entity
	 *
	 * @return string
	 * @throws SmartlingFileDownloadException
	 */
	public function downloadFile ( SubmissionEntity $entity ) {

		$profile = $this->getConfigurationProfile( $entity );

		$this->setApi( $profile );

		$actionMark = dechex( crc32( microtime() ) ); // simple short note

		$logMessage = vsprintf( 'Session [%s]. Starting file (%s) download for blog_id %s and content-type %s for translation to %s',
			array (
				$actionMark,
				$entity->getFileUri(),
				$entity->getSourceBlogId(),
				$entity->getContentType(),
				$entity->getTargetLocale()
			) );

		$this->logger->info( $logMessage );

		$smartlingLocale = '';

		foreach ( $profile->getTargetLocales() as $locale ) {
			if ( $locale->getBlogId() === $entity->getTargetBlogId() ) {
				$smartlingLocale = $locale->getSmartlingLocale();
				break;
			}
		}

		// Try to download file.
		$requestResultRaw = $this->api->downloadFile(
			$entity->getFileUri(),
			$smartlingLocale,
			array (
				'retrievalType' => $profile->getRetrievalType(),
			)
		);

		// zero length response
		if ( '' === trim( $requestResultRaw ) ) {
			$logMessage = vsprintf(
				'Session [%s]. Empty or bad formatted response received by SmartlingAPI with settings: \n %s',
				array ( $actionMark, json_encode( $profile->toArray(), JSON_PRETTY_PRINT ) ) );
			$this->logger->error( $logMessage );
			throw new SmartlingFileDownloadException( $logMessage );
		} else {
			$message = vsprintf( 'Session [%s]. File downloaded. size: %s bytes, content: \'%s\'',
				array (
					$actionMark,
					strlen( $requestResultRaw ),
					$requestResultRaw,
				)
			);
			$this->logger->debug( $message );
		}

		$requestResult = json_decode( $requestResultRaw );

		if ( null !== $requestResult && isset( $requestResult->response ) && isset( $requestResult->response->code ) ) {
			// error happened

			$code     = isset( $requestResult->response->code ) ? $requestResult->response->code : 'unknown';
			$messages = isset( $requestResult->response->messages ) ? $requestResult->response->messages : array ();

			$logMessage = vsprintf(
				'Session [%s]. Trying to download file: \n Project ID : %s\n Action : %s\n URI : %s\n Locale : %s\n Error : response code -> %s and message -> %s',
				array (
					$actionMark,
					$profile->getProjectId(),
					'download',
					$entity->getFileUri(),
					$entity->getTargetLocale(),
					$code,
					implode( ' || ', $messages )
				)
			);

			$infoMessage = vsprintf(
				'Error happened while downloading translation of %s file for %s locale. Smartling response: %s; %s.',
				array (
					$entity->getFileUri(),
					$entity->getTargetLocale(),
					$code,
					implode( ', ', $messages )
				)
			);

			$this->logger->error( $logMessage );
			throw new SmartlingFileDownloadException( $infoMessage );
		}

		return $requestResultRaw;
	}

	/**
	 * @param SubmissionEntity $entity
	 *
	 * @return SubmissionEntity
	 * @throws SmartlingFileDownloadException
	 * @throws SmartlingNetworkException
	 */
	public function getStatus ( SubmissionEntity $entity ) {
		if ( null === $entity ) {
			$message = vsprintf(
				'Received null instead of SubmissionEntity instance, called from %s',
				array ( get_called_class() )
			);
			$this->logger->error( $message );
			throw new \InvalidArgumentException( $message );
		}

		$profile = $this->getConfigurationProfile( $entity );

		$this->setApi( $profile );

		$rawResponse = $this->api->getStatus( $entity->getFileUri(),
			$this->getSmartLingLocale( $profile, $entity->getTargetBlogId() ) );

		$status_result = json_decode( $rawResponse );

		if ( null === $status_result ) {
			$message = vsprintf( 'File status commend: downloaded json is broken. JSON: \'%s\'',
				array ( $rawResponse ) );
			$this->logger->error( $message );
			throw new SmartlingNetworkException( $message );
		}

		if ( ( 'SUCCESS' !== $this->api->getCodeStatus() ) || ! isset( $status_result->response->data ) ) {
			$code     = '';
			$messages = array ();
			if ( isset( $status_result->response ) ) {
				$code     = isset( $status_result->response->code ) ? $status_result->response->code : 'unknown';
				$messages = isset( $status_result->response->messages ) ? $status_result->response->messages : array ();
			}

			$logMessage = vsprintf(
				'Smartling checks status for %s id - %s: \n Project ID : %s\n Action : %s\n URI : %s\n Locale : %s\n Error : response code -> %s and message -> %s',
				array (
					$entity->getContentType(),
					$entity->getSourceId(),
					$profile->getProjectId(),
					'status',
					$entity->getFileUri(),
					$entity->getTargetLocale(),
					$code,
					implode( ' || ', $messages )
				)
			);
			$this->logger->error( $logMessage );
			throw new SmartlingFileDownloadException( $logMessage );
		}

		$logMessage = vsprintf(
			'Smartling checks status for %s id - %s. approvedString = %s, completedString = %s',
			array (
				$entity->getContentType(),
				$entity->getSourceId(),
				$status_result->response->data->approvedStringCount,
				$status_result->response->data->completedStringCount
			)
		);

		$this->logger->info( $logMessage );

		$entity->setApprovedStringCount( $status_result->response->data->approvedStringCount );
		$entity->setCompletedStringCount( $status_result->response->data->completedStringCount );
		$entity->setWordCount( $status_result->response->data->wordCount );

		return $entity;
	}

	/**
	 * @param string $locale
	 *
	 * @return bool
	 */
	public function testConnection ( $locale ) {
		$server_response = $this->api->getList( $locale, array ( 'limit' => 1 ) );

		$result = 'SUCCESS' === $this->api->getCodeStatus();

		if ( ! $result ) {
			$logMessage = vsprintf( 'Connection test for project: %s and locale: %s FAILED and returned the following result: %s.',
				array (
					$this->settings->getAccountInfo()->getProjectId(),
					$locale,
					$server_response
				) );

			$this->logger->error( $logMessage );

		}

		return $result;
	}

	/**
	 * @param ConfigurationProfileEntity $profile
	 * @param                            $targetBlog
	 *
	 * @return string
	 */
	private function getSmartLingLocale ( ConfigurationProfileEntity $profile, $targetBlog ) {
		$locale = '';

		$locales = $profile->getTargetLocales();
		foreach ( $locales as $item ) {
			if ( $targetBlog === $item->getBlogId() ) {
				$locale = $item->getSmartlingLocale();
				break;
			}
		}

		return $locale;
	}

	/**
	 * @param SubmissionEntity $entity
	 *
	 * @return array
	 */
	private function buildParams ( SubmissionEntity $entity ) {
		$paramBuilder = new FileUploadParameterBuilder();

		$profile = $this->getConfigurationProfile( $entity );

		$this->setApi( $profile );

		$paramBuilder->setFileUri( $entity->getFileUri() )
		             ->setFileType( 'xml' )
		             ->setApproved( 0 )
		             ->setOverwriteApprovedLocales( 0 );

		if ( $profile->getAutoAuthorize() ) {
			$slocale = '';
			foreach ( $profile->getTargetLocales() as $locale ) {
				if ( $locale->getBlogId() === $entity->getTargetBlogId() ) {
					$slocale = $locale->getSmartlingLocale();
				}
			}
			$paramBuilder->setLocalesToApprove( array ( $slocale ) );
		}

		return $paramBuilder->buildParameters();
	}

	/**
	 * @param SubmissionEntity $entity
	 * @param string           $xmlString
	 *
	 * @param string           $filename
	 *
	 * @return bool
	 * @throws SmartlingFileUploadException
	 */
	public function uploadContent ( SubmissionEntity $entity, $xmlString = '', $filename = '' ) {
		$params = $this->buildParams( $entity );

		$profile = $this->getConfigurationProfile( $entity );

		$this->setApi( $profile );

		if ( '' !== $xmlString ) {
			$uploadResultRaw = $this->api->uploadContent( $xmlString, $params );
		} else {
			$uploadResultRaw = $this->api->uploadFile( $filename, $params );
		}

		$uploadResult = json_decode( $uploadResultRaw );
		if ( 'SUCCESS' === $this->api->getCodeStatus() ) {

			$message = vsprintf(
				'Smartling uploaded %s for locale: %s',
				array (
					$entity->getFileUri(),
					$entity->getTargetLocale()
				)
			);

			$this->logger->info( $message );

			return true;

		} elseif ( is_object( $uploadResult ) ) {
			$_params = array ();
			foreach ( $params as $param => $value ) {
				$_params[] = vsprintf( '%s => %s', array ( $param, $value ) );
			}

			$code     = '';
			$messages = array ();
			if ( isset( $uploadResult->response ) ) {
				$code     = isset( $uploadResult->response->code ) ? $uploadResult->response->code : 'unknown';
				$messages = isset( $uploadResult->response->messages ) ? $uploadResult->response->messages : array ();
			}

			$message = vsprintf( 'Smartling failed to upload xml file: \nProject Id: %s\nAction: %s\nURI: \nLocale: %s\nError: response code -> %s and message -> %s\nUpload params: %s',
				array (
					$profile->getProjectId(),
					'upload',
					$entity->getFileUri(),
					$entity->getTargetLocale(),
					$code,
					implode( ' || ', $messages ),
					implode( ' | ', $_params )
				) );

			$this->logger->error( $message );

			throw new SmartlingFileUploadException( $message );
		}
	}

	/**
	 * @inheritdoc
	 */
	public function getSupportedLocales ( ConfigurationProfileEntity $profile ) {

		$this->setApi( $profile );

		$rawResponse = $this->api->getSupportedLocales();
		$oResponse   = json_decode( $rawResponse );

		$supportedLocales = array ();

		switch ( true ) {
			case ( false === $oResponse ) : {
				$message = vsprintf( 'Failed decoding response message. Message:\'%s\'', array ( $rawResponse ) );
				$this->logger->error( $message );
				break;
			}
			case ( ! isset( $oResponse->response ) ): {
				$message = vsprintf( 'Response does not contain body. Message:\'%s\'', array ( $rawResponse ) );
				$this->logger->error( $message );
				break;
			}
			case ( ( ! isset( $oResponse->response->code ) ) || ( 'SUCCESS' !== $oResponse->response->code ) ): {
				$message = vsprintf( 'Response has no SUCCESS response code. Message:\'%s\'', array ( $rawResponse ) );
				$this->logger->error( $message );
				break;
			}
			case ( ( isset( $oResponse->response->messages ) ) && ( 0 < count( $oResponse->response->messages ) ) ): {
				$message = vsprintf( 'Response has error messages. Message:\'%s\'', array ( $rawResponse ) );
				$this->logger->error( $message );
				break;
			}
			case ( ( isset( $oResponse->response->data ) ) && isset( $oResponse->response->data->locales ) && is_array( $oResponse->response->data->locales ) ): {
				foreach ( $oResponse->response->data->locales as $localeDef ) {
					$supportedLocales[ $localeDef->locale ] = vsprintf( '%s [%s]',
						array ( $localeDef->name, $localeDef->translated ) );
				}
				break;
			}
		}

		return $supportedLocales;
	}
}
