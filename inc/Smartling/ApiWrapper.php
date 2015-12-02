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
		$message = vsprintf( 'No active profile found for main blog %s', [ $mainBlogId ] );
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

		$this->logger->info( vsprintf(
			'Starting file \'%s\' download for entity = \'%s\', blog = \'%s\', id = \'%s\', locale = \'%s\'',
			[
				$entity->getFileUri(),
				$entity->getContentType(),
				$entity->getSourceBlogId(),
				$entity->getSourceId(),
				$entity->getTargetLocale(),
			]
		) );

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
			[
				'retrievalType' => $profile->getRetrievalType(),
			]
		);

		// zero length response
		if ( '' === trim( $requestResultRaw ) ) {
			$msg = vsprintf(
				'Empty or bad formatted response received while downloading file \'%s\' for entity = \'%s\', blog = \'%s\', id = \'%s\', locale = \'%s\'. Profile dump:' . PHP_EOL . '%s',
				[
					$entity->getFileUri(),
					$entity->getContentType(),
					$entity->getSourceBlogId(),
					$entity->getSourceId(),
					$entity->getTargetLocale(),
					base64_encode( json_encode( $profile->toArray() ) ),
				]
			);

			$this->logger->error( $msg );
			throw new SmartlingFileDownloadException( $msg );
		} else {
			$msg = vsprintf(
				'File \'%s\'downloaded. Size = \'%s\'. Dump = \'%s\'',
				[
					$entity->getFileUri(),
					strlen( $requestResultRaw ),
					base64_encode( $requestResultRaw ),
				]
			);

			$this->logger->debug( $msg );
		}

		$requestResult = json_decode( $requestResultRaw );

		if ( null !== $requestResult && isset( $requestResult->response ) && isset( $requestResult->response->code ) ) {
			// error happened

			$code     = isset( $requestResult->response->code ) ? $requestResult->response->code : 'unknown';
			$messages = isset( $requestResult->response->messages ) ? $requestResult->response->messages : [ ];

			$infoMessage = vsprintf(
				'Error while downloading file \'%s\' for locale = \'%s\', code = \'%s\', messages = \'%s\'.',
				[
					$entity->getFileUri(),
					$entity->getTargetLocale(),
					$code,
					implode( '; ', $messages ),
				]
			);

			$logMessage = $infoMessage . vsprintf(
					' Profile dump = \'%s\'',
					[
						base64_encode( json_encode( $profile->toArray() ) ),
					]
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
				[ get_called_class() ]
			);
			$this->logger->error( $message );
			throw new \InvalidArgumentException( $message );
		}

		$profile = $this->getConfigurationProfile( $entity );

		$this->setApi( $profile );

		$rawResponse = $this->api->getStatus(
			$entity->getFileUri(),
			$this->getSmartLingLocale(
				$profile,
				$entity->getTargetBlogId() ) );

		$status_result = json_decode( $rawResponse );

		if ( null === $status_result ) {
			$msg = vsprintf(
				'Empty or bad formatted response received while checking status of the file \'%s\' for entity = \'%s\', blog = \'%s\', id = \'%s\', locale = \'%s\'. Profile dump:' . PHP_EOL . '%s',
				[
					$entity->getFileUri(),
					$entity->getContentType(),
					$entity->getSourceBlogId(),
					$entity->getSourceId(),
					$entity->getTargetLocale(),
					base64_encode( json_encode( $profile->toArray() ) ),
				]
			);

			$this->logger->error( $msg );
			throw new SmartlingNetworkException( $msg );
		}

		if ( ( 'SUCCESS' !== $this->api->getCodeStatus() ) || ! isset( $status_result->response->data ) ) {
			$code     = '';
			$messages = [ ];
			if ( isset( $status_result->response ) ) {
				$code     = isset( $status_result->response->code ) ? $status_result->response->code : 'unknown';
				$messages = isset( $status_result->response->messages ) ? $status_result->response->messages : [ ];
			}

			$infoMessage = vsprintf(
				'Error while checking status of a file \'%s\' for locale = \'%s\', code = \'%s\', messages = \'%s\'.',
				[
					$entity->getFileUri(),
					$entity->getTargetLocale(),
					$code,
					implode( '; ', $messages ),
				]
			);

			$logMessage = $infoMessage . vsprintf(
					' Profile dump = \'%s\'',
					[
						base64_encode( json_encode( $profile->toArray() ) ),
					]
				);

			$this->logger->error( $logMessage );
			throw new SmartlingFileDownloadException( $infoMessage );
		}

		$logMessage = vsprintf(
			'Status checked for file \'%s\' for locale = \'%s\', approvedStrings = \'%s\', completedStrings = \'%s\'.',
			[
				$entity->getFileUri(),
				$entity->getTargetLocale(),
				$status_result->response->data->approvedStringCount,
				$status_result->response->data->completedStringCount,
			]
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
		$server_response = $this->api->getList( $locale, [ 'limit' => 1 ] );

		$result = 'SUCCESS' === $this->api->getCodeStatus();

		if ( ! $result ) {
			$logMessage = vsprintf( 'Connection test for project: %s and locale: %s FAILED and returned the following result: %s.',
				[
					$this->settings->getAccountInfo()->getProjectId(),
					$locale,
					$server_response,
				] );

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
			$paramBuilder->setLocalesToApprove( [ $slocale ] );
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

		$this->logger->info( vsprintf(
			'Starting file \'%s\' upload for entity = \'%s\', blog = \'%s\', id = \'%s\', locale = \'%s\'',
			[
				$entity->getFileUri(),
				$entity->getContentType(),
				$entity->getSourceBlogId(),
				$entity->getSourceId(),
				$entity->getTargetLocale(),
			]
		) );

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
				[
					$entity->getFileUri(),
					$entity->getTargetLocale(),
				]
			);

			$this->logger->info( $message );

			return true;

		} elseif ( is_object( $uploadResult ) ) {
			$_params = [ ];
			foreach ( $params as $param => $value ) {
				$_params[] = vsprintf( '%s => %s', [ $param, $value ] );
			}

			$code     = '';
			$messages = [ ];

			if ( isset( $uploadResult->response ) ) {
				$code     = isset( $uploadResult->response->code ) ? $uploadResult->response->code : 'unknown';
				$messages = isset( $uploadResult->response->messages ) ? $uploadResult->response->messages : [ ];
			}

			$msg = vsprintf(
				'Error while uploading  file \'%s\' for locale = \'%s\', code=\'%s\', messages=\'%s\'. Profile dump:' . PHP_EOL . '%s',
				[
					$entity->getFileUri(),
					$entity->getTargetLocale(),
					$code,
					implode( '; ', $messages ),
					base64_encode( json_encode( $profile->toArray() ) ),
				]
			);

			$this->logger->error( $msg );
			throw new SmartlingFileUploadException( $msg );
		}
	}

	/**
	 * @inheritdoc
	 */
	public function getSupportedLocales ( ConfigurationProfileEntity $profile ) {

		$this->setApi( $profile );

		$rawResponse = $this->api->getSupportedLocales();
		$oResponse   = json_decode( $rawResponse );

		$supportedLocales = [ ];

		switch ( true ) {
			case ( false === $oResponse ) : {
				$message = vsprintf( 'Failed decoding response message. Message:\'%s\'', [ $rawResponse ] );
				$this->logger->error( $message );
				break;
			}
			case ( ! isset( $oResponse->response ) ): {
				$message = vsprintf( 'Response does not contain body. Message:\'%s\'', [ $rawResponse ] );
				$this->logger->error( $message );
				break;
			}
			case ( ( ! isset( $oResponse->response->code ) ) || ( 'SUCCESS' !== $oResponse->response->code ) ): {
				$message = vsprintf( 'Response has no SUCCESS response code. Message:\'%s\'', [ $rawResponse ] );
				$this->logger->error( $message );
				break;
			}
			case ( ( isset( $oResponse->response->messages ) ) && ( 0 < count( $oResponse->response->messages ) ) ): {
				$message = vsprintf( 'Response has error messages. Message:\'%s\'', [ $rawResponse ] );
				$this->logger->error( $message );
				break;
			}
			case ( ( isset( $oResponse->response->data ) ) && isset( $oResponse->response->data->locales ) && is_array( $oResponse->response->data->locales ) ): {
				foreach ( $oResponse->response->data->locales as $localeDef ) {
					$supportedLocales[ $localeDef->locale ] = vsprintf( '%s [%s]',
						[ $localeDef->name, $localeDef->translated ] );
				}
				break;
			}
		}

		return $supportedLocales;
	}
}
