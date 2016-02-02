<?php

namespace Smartling;

use Psr\Log\LoggerInterface;
use Smartling\AuthApi\AuthTokenProvider;
use Smartling\Exception\SmartligFileDownloadException;
use Smartling\Exception\SmartlingDbException;
use Smartling\Exception\SmartlingFileUploadException;
use Smartling\Exception\SmartlingNetworkException;
use Smartling\File\FileApi;
use Smartling\File\Params\DownloadFileParameters;
use Smartling\File\Params\UploadFileParameters;
use Smartling\Project\ProjectApi;
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
	 * @var string
	 */
	private $pluginName = '';

	/**
	 * @var string
	 */
	private $pluginVersion = '';

	/**
	 * @return string
	 */
	public function getPluginName () {
		return $this->pluginName;
	}

	/**
	 * @param string $pluginName
	 */
	public function setPluginName ( $pluginName ) {
		$this->pluginName = $pluginName;
	}

	/**
	 * @return string
	 */
	public function getPluginVersion () {
		return $this->pluginVersion;
	}

	/**
	 * @param string $pluginVersion
	 */
	public function setPluginVersion ( $pluginVersion ) {
		$this->pluginVersion = $pluginVersion;
	}

	/**
	 * @return SettingsManager
	 */
	public function getSettings () {
		return $this->settings;
	}

	/**
	 * @param SettingsManager $settings
	 */
	public function setSettings ( $settings ) {
		$this->settings = $settings;
	}

	/**
	 * @return LoggerInterface
	 */
	public function getLogger () {
		return $this->logger;
	}

	/**
	 * @param LoggerInterface $logger
	 */
	public function setLogger ( $logger ) {
		$this->logger = $logger;
	}

	/**
	 * ApiWrapper constructor.
	 *
	 * @param SettingsManager $manager
	 * @param LoggerInterface $logger
	 * @param string          $pluginName
	 * @param string          $pluginVersion
	 */
	public function __construct ( SettingsManager $manager, LoggerInterface $logger, $pluginName, $pluginVersion ) {
		$this->setSettings( $manager );
		$this->setLogger( $logger );
		$this->setPluginName( $pluginName );
		$this->setPluginVersion( $pluginVersion );
	}

	/**
	 * @param SubmissionEntity $submission
	 *
	 * @return ConfigurationProfileEntity
	 * @throws SmartlingDbException
	 */
	private function getConfigurationProfile ( SubmissionEntity $submission ) {
		$mainBlogId = $submission->getSourceBlogId();

		$possibleProfiles = $this->getSettings()->findEntityByMainLocale( $mainBlogId );

		if ( 0 < count( $possibleProfiles ) ) {
			return reset( $possibleProfiles );
		}
		$message = vsprintf( 'No active profile found for main blog %s', [ $mainBlogId ] );
		$this->getLogger()->warning( $message );
		throw new SmartlingDbException( $message );
	}

	/**
	 * @param ConfigurationProfileEntity $profile
	 *
	 * @return AuthTokenProvider
	 */
	private function getAuthProvider ( ConfigurationProfileEntity $profile ) {
		$authProvider = AuthTokenProvider::create(
			$profile->getUserIdentifier(),
			$profile->getSecretKey(),
			$this->getLogger()
		);

		return $authProvider;
	}

	/**
	 * @param SubmissionEntity $entity
	 *
	 * @return string
	 * @throws SmartligFileDownloadException
	 */
	public function downloadFile ( SubmissionEntity $entity ) {
		try {
			$profile = $this->getConfigurationProfile( $entity );

			$api = FileApi::create(
				$this->getAuthProvider( $profile ),
				$profile->getProjectId(),
				$this->getLogger()
			);

			$this->getLogger()->info( vsprintf(
				'Starting file \'%s\' download for entity = \'%s\', blog = \'%s\', id = \'%s\', locale = \'%s\'',
				[
					$entity->getFileUri(),
					$entity->getContentType(),
					$entity->getSourceBlogId(),
					$entity->getSourceId(),
					$entity->getTargetLocale(),
				]
			) );

			$params = new DownloadFileParameters();

			$params->setRetrievalType( $profile->getRetrievalType() );

			$result = $api->downloadFile(
				$entity->getFileUri(),
				$this->getSmartlingLocaleBySubmission( $entity ),
				$params
			);

			return $result;

		} catch ( \Exception $e ) {
			$this->getLogger()->error( $e->getMessage() );
			throw new SmartligFileDownloadException( $e->getMessage() );

		}
	}

	/**
	 * @param SubmissionEntity $entity
	 *
	 * @return SubmissionEntity
	 * @throws SmartlingNetworkException
	 */
	public function getStatus ( SubmissionEntity $entity ) {
		try {
			$locale = $this->getSmartlingLocaleBySubmission( $entity );

			$profile = $this->getConfigurationProfile( $entity );

			$api = FileApi::create(
				$this->getAuthProvider( $profile ),
				$profile->getProjectId(),
				$this->getLogger()
			);

			$data = $api->getStatus( $entity->getFileUri(), $locale );

			$entity->setApprovedStringCount( $data['completedStringCount'] + $data['authorizedStringCount'] );
			$entity->setCompletedStringCount( $data['completedStringCount'] );
			$entity->setWordCount( $data['totalWordCount'] );

			return $entity;

		} catch ( \Exception $e ) {
			$this->logger->error( $e->getMessage() );
			throw new SmartlingNetworkException( $e->getMessage() );

		}
	}

	/**
	 * @param ConfigurationProfileEntity $profile
	 *
	 * @return bool
	 * @throws SmartlingNetworkException
	 */
	public function testConnection ( ConfigurationProfileEntity $profile ) {
		try {
			$api = FileApi::create(
				$this->getAuthProvider( $profile ),
				$profile->getProjectId(),
				$this->getLogger()
			);

			$api->getList();

			return true;
		} catch ( \Exception $e ) {
			$this->logger->error( $e->getMessage() );
			throw new SmartlingNetworkException( $e->getMessage() );
		}
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

	private function getSmartlingLocaleBySubmission ( SubmissionEntity $entity ) {
		$profile = $this->getConfigurationProfile( $entity );

		return $this->getSmartLingLocale( $profile, $entity->getTargetBlogId() );
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
		$this->getLogger()->info( vsprintf(
			'Starting file \'%s\' upload for entity = \'%s\', blog = \'%s\', id = \'%s\', locale = \'%s\'',
			[
				$entity->getFileUri(),
				$entity->getContentType(),
				$entity->getSourceBlogId(),
				$entity->getSourceId(),
				$entity->getTargetLocale(),
			]
		) );
		try {
			$profile = $this->getConfigurationProfile( $entity );

			$api = FileApi::create(
				$this->getAuthProvider( $profile ),
				$profile->getProjectId(),
				$this->getLogger()
			);

			$params = new UploadFileParameters( $this->getPluginName(), $this->getPluginVersion() );

			// We always explicit say do not authorize for all locales
			$params->setAuthorized( false );
			if ( $profile->getAutoAuthorize() ) {
				// Authorize for locale only if user chooses this in settings
				$locale = $this->getSmartlingLocaleBySubmission( $entity );
				$params->setLocalesToApprove( $locale );
			}

			$res = $api->uploadFile(
				$filename,
				$entity->getFileUri(),
				'xml',
				$params );

			$message = vsprintf(
				'Smartling uploaded %s for locale: %s',
				[
					$entity->getFileUri(),
					$entity->getTargetLocale(),
				]
			);

			$this->logger->info( $message );

			return true;

		} catch ( \Exception $e ) {
			throw new SmartlingFileUploadException( $e->getMessage() );
		}
	}

	/**
	 * @inheritdoc
	 */
	public function getSupportedLocales ( ConfigurationProfileEntity $profile ) {

		$supportedLocales = [ ];

		try {




			$api = ProjectApi::create(
				$this->getAuthProvider( $profile ),
				$profile->getProjectId(),
				$this->getLogger()
			);

			$locales = $api->getProjectDetails();

			foreach ( $locales['targetLocales'] as $locale ) {
				$supportedLocales[ $locale['localeId'] ] = $locale['description'];
			}
		} catch ( \Exception $e ) {
			$message = vsprintf( 'Response has error messages. Message:\'%s\'', [ $e->getMessage() ] );
			$this->logger->error( $message );
		}

		return $supportedLocales;
	}
}
