<?php

namespace Smartling;

use Psr\Log\LoggerInterface;
use Smartling\Exception\SmartlingFileDownloadException;
use Smartling\Exception\SmartlingFileUploadException;
use Smartling\Exception\SmartlingNetworkException;
use Smartling\Helpers\AccountInfo;
use Smartling\SDK\FileUploadParameterBuilder;
use Smartling\SDK\SmartlingAPI;
use Smartling\Submissions\SubmissionEntity;

/**
 * Class ApiWrapper
 *
 * @package Smartling
 */
class ApiWrapper implements ApiWrapperInterface {
	/**
	 * @var AccountInfo
	 */
	private $settings;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var SmartlingAPI
	 */
	private $api;

	/**
	 * @param AccountInfo     $settings
	 * @param LoggerInterface $logger
	 */
	public function __construct ( AccountInfo $settings, LoggerInterface $logger ) {
		$this->settings = $settings;
		$this->logger   = $logger;

		$this->setApi(
			new SmartlingAPI(
				$settings->getApiUrl(),
				$settings->getKey(),
				$settings->getProjectId(),
				SmartlingAPI::PRODUCTION_MODE // TODO: where get the mode
			)
		);
	}

	/**
	 * @param SmartlingAPI $api
	 */
	public function setApi ( SmartlingAPI $api ) {
		$this->api = $api;
	}

	/**
	 * @param SubmissionEntity $entity
	 *
	 * @return string
	 * @throws SmartlingFileDownloadException
	 */
	public function downloadFile ( SubmissionEntity $entity ) {

		$actionMark = dechex( crc32( microtime() ) ); // simple short note

		$logMessage = vsprintf( 'Session [%s]. Starting file (%s) download for blog_id %s and content-type %s for translation to %s',
			array (
				$actionMark,
				$entity->getFileUri(),
				$entity->getSourceBlog(),
				$entity->getContentType(),
				$entity->getTargetLocale()
			) );

		$this->logger->info( $logMessage, array ( __FILE__, __LINE__ ) );

		// Try to download file.
		$requestResultRaw = $this->api->downloadFile( $entity->getFileUri(), $entity->getTargetLocale() );

		// zero length response
		if ( 0 === strlen( trim( $requestResultRaw ) ) ) {
			$logMessage = vsprintf(
				'Session [%s]. Empty or bad formatted response received by SmartlingAPI with settings: \n %s',
				array ( $actionMark, json_encode( $this->settings->toArray(), JSON_PRETTY_PRINT ) ) );

			$this->logger->error( $logMessage, array ( __FILE__, __LINE__ ) );

			throw new SmartlingFileDownloadException( $logMessage, 0, __FILE__, __LINE__ );
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
					$this->settings->getProjectId(),
					'download',
					$entity->getFileUri(),
					$entity->getTargetLocale(),
					$code,
					implode( ' || ', $messages )
				)
			);

			$this->logger->error( $logMessage, array ( __FILE__, __LINE__ ) );

			throw new SmartlingFileDownloadException( $logMessage, 0, __FILE__, __LINE__ );
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

			$message = vsprintf( '%s::%s called by %s received null instead of instance of %', array (
				__CLASS__,
				__METHOD__,
				get_called_class(),
				'SubmissionEntity'
			) );

			$this->logger->error( $message, array ( __FILE__, __LINE__ ) );

			throw new \InvalidArgumentException( $message, 0, __FILE__, __LINE__ );

		}

		$rawResponse = $this->api->getStatus( $entity->getFileUri(), $entity->getTargetLocale() );

		$status_result = json_decode( $rawResponse );

		if ( null === $status_result ) {

			$message = vsprintf( 'File status commend: downloaded json is broken. JSON: \'%s\'',
				array ( $rawResponse ) );

			$this->logger->error( $message, array ( __FILE__, __LINE__ ) );

			throw new SmartlingNetworkException( $message, 0, __FILE__, __LINE__ );
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
					$entity->getSourceGUID(),
					$this->settings->getProjectId(),
					'status',
					$entity->getFileUri(),
					$entity->getTargetLocale(),
					$code,
					implode( ' || ', $messages )
				)
			);

			$this->logger->error( $logMessage, array ( __FILE__, __LINE__ ) );

			throw new SmartlingFileDownloadException( $logMessage, 0, __FILE__, __LINE__ );

		}

		$logMessage = vsprintf(
			'Smartling checks status for %s id - %s. approvedString = %s, completedString = %s',
			array (
				$entity->getContentType(),
				$entity->getSourceGUID(),
				$status_result->response->data->approvedStringCount,
				$status_result->response->data->completedStringCount
			)
		);

		$this->logger->info( $logMessage, array ( __FILE__, __LINE__ ) );

		$entity->setApprovedStringCount( $status_result->response->data->approvedStringCount );
		$entity->setCompletedStringCount( $status_result->response->data->completedStringCount );

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
					$this->settings->getProjectId(),
					$locale,
					$server_response
				) );

			$this->logger->error( $logMessage, array ( __FILE__, __LINE__ ) );

		}

		return $result;
	}

	/**
	 * @param SubmissionEntity $entity
	 * @param                  $xmlString
	 *
	 * @return bool
	 * @throws SmartlingFileUploadException
	 */
	public function uploadFile ( SubmissionEntity $entity, $xmlString ) {

		$paramBuilder = new FileUploadParameterBuilder();

		$paramBuilder->setFileUri( $entity->getFileUri() )
		             ->setFileType( 'application/xml' )
		             ->setApproved( 0 )
		             ->setOverwriteApprovedLocales( 0 );

		if ( $this->settings->getAutoAuthorize() ) {
			$paramBuilder->setLocalesToApprove( array ( $entity->getTargetLocale() ) );
		}

		if ( $this->settings->getCallBackUrl() ) {
			$paramBuilder->setCallbackUrl( $this->settings->getCallBackUrl() );
		}

		$params = $paramBuilder->buildParameters();

		$uploadResultRaw = $this->api->uploadContent( $xmlString, $params );

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
					$this->settings->getProjectId(),
					'upload',
					$entity->getFileUri(),
					$entity->getTargetLocale(),
					$code,
					implode( ' || ', $messages ),
					implode( ' | ', $_params )
				) );

			$this->logger->error( $message, array ( __FILE__, __LINE__ ) );

			throw new SmartlingFileUploadException( $message, 0, __FILE__, __LINE__ );
		}
	}
}
