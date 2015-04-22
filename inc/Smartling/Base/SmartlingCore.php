<?php
namespace Smartling\Base;

use Exception;
use Psr\Log\LoggerInterface;

use Smartling\ApiWrapperInterface;
use Smartling\Bootstrap;
use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\DbAl\WordpressContentEntities\EntityAbstract;
use Smartling\Exception\SmartlingDbException;
use Smartling\Exception\SmartlingExceptionAbstract;
use Smartling\Helpers\DateTimeHelper;
use Smartling\Helpers\SiteHelper;
use Smartling\Helpers\XmlEncoder;
use Smartling\Processors\ContentEntitiesIOFactory;
use Smartling\Processors\PropertyDescriptor;
use Smartling\Processors\PropertyProcessors\PropertyProcessorFactory;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

/**
 * Class SmartlingCore
 *
 * @package Smartling\Base
 */
class SmartlingCore {

	/**
	 * Mode to send data to smartling directly
	 */
	const SEND_MODE_STREAM = 1;

	/**
	 * Mode to send data to smartling via temporary file
	 */
	const SEND_MODE_FILE = 2;

	/**
	 * current mode to send data to Smartling
	 */
	const SEND_MODE = self::SEND_MODE_FILE;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var SubmissionManager
	 */
	private $submissionManager;

	/**
	 * @var SettingsManager
	 */
	private $settings;

	/**
	 * @var SiteHelper
	 */
	private $siteHelper;

	/**
	 * @var ApiWrapperInterface
	 */
	private $apiWrapper;

	/**
	 * @var ContentEntitiesIOFactory
	 */
	private $contentIoFactory;

	/**
	 * @var LocalizationPluginProxyInterface
	 */
	private $multilangProxy;

	/**
	 * @var PropertyProcessorFactory
	 */
	private $processorFactory;

	/**
	 * @return ApiWrapperInterface
	 */
	public function getApiWrapper () {
		return $this->apiWrapper;
	}

	/**
	 * @param ApiWrapperInterface $apiWrapper
	 */
	public function setApiWrapper ( ApiWrapperInterface $apiWrapper ) {
		$this->apiWrapper = $apiWrapper;
	}

	/**
	 * @return LocalizationPluginProxyInterface
	 */
	public function getMultilangProxy () {
		return $this->multilangProxy;
	}

	/**
	 * @param LocalizationPluginProxyInterface $multilangProxy
	 */
	public function setMultilangProxy ( $multilangProxy ) {
		$this->multilangProxy = $multilangProxy;
	}

	/**
	 * @return SiteHelper
	 */
	public function getSiteHelper () {
		return $this->siteHelper;
	}

	/**
	 * @param SiteHelper $siteHelper
	 */
	public function setSiteHelper ( SiteHelper $siteHelper ) {
		$this->siteHelper = $siteHelper;
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
	public function setLogger ( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * @return PropertyProcessorFactory
	 */
	public function getProcessorFactory () {
		return $this->processorFactory;
	}

	/**
	 * @param PropertyProcessorFactory $processorFactory
	 */
	public function setProcessorFactory ( $processorFactory ) {
		$this->processorFactory = $processorFactory;
	}

	/**
	 * @return SubmissionManager
	 */
	public function getSubmissionManager () {
		return $this->submissionManager;
	}

	/**
	 * @param SubmissionManager $submissionManager
	 */
	public function setSubmissionManager ( SubmissionManager $submissionManager ) {
		$this->submissionManager = $submissionManager;
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
	public function setSettings ( SettingsManager $settings ) {
		$this->settings = $settings;
	}

	/**
	 * @return ContentEntitiesIOFactory
	 */
	public function getContentIoFactory () {
		return $this->contentIoFactory;
	}

	/**
	 * @param ContentEntitiesIOFactory $contentIoFactory
	 */
	public function setContentIoFactory ( $contentIoFactory ) {
		$this->contentIoFactory = $contentIoFactory;
	}

	public function sendForTranslationBySubmissionId ( $id ) {
		return $this->sendForTranslationBySubmission( $this->loadSubmissionEntityById( $id ) );
	}

	/**
	 * @param PropertyDescriptor[] $descriptors
	 * @param array                $source
	 */
	private function fillPropertyDescriptors ( array $descriptors, array $source ) {
		foreach ( $descriptors as $descriptor ) {

			$index = $descriptor->isMeta() ? 'meta' : 'entity';
			$name  = $descriptor->getName();

			if ( array_key_exists( $name, $source[ $index ] ) ) {
				$descriptor->setValue( $source[ $index ][ $name ] );
			}

		}
	}

	public function sendForTranslationBySubmission ( SubmissionEntity $submission ) {
		$contentEntity = $this->readContentEntity( $submission );

		$submission->setSourceContentHash( $contentEntity->calculateHash() );
		$submission->setSourceTitle( $contentEntity->getTitle() );

		if ( null === $submission->getId() ) {
			// generate URI
			$submission->getFileUri();
			$submission = $this->getSubmissionManager()->storeEntity( $submission );
		}

		$source = array (
			'entity' => $contentEntity->toArray(),
			'meta'   => $contentEntity->getMetadata()
		);

		$fieldsForTranslation = $this->getTranslatableFields( $submission->getContentType() );

		$this->fillPropertyDescriptors( $fieldsForTranslation, $source );

		$xml = XmlEncoder::xmlEncode( $fieldsForTranslation, $this->getProcessorFactory() );

		$result = false;


		try {
			$result = self::SEND_MODE === self::SEND_MODE_FILE
				? $this->sendFile( $submission, $xml )
				: $this->sendStream( $submission, $xml );

			$submission->setStatus( SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS );

		} catch ( Exception $e ) {
			$this->getLogger()->error($e->getMessage());
			$submission->setStatus( SubmissionEntity::SUBMISSION_STATUS_FAILED );
		}

		$submission = $this->getSubmissionManager()->storeEntity( $submission );

		return $result;
	}

	/**
	 * @param string   $contentType
	 * @param int      $sourceBlog
	 * @param int      $sourceEntity
	 * @param int      $targetBlog
	 * @param int|null $targetEntity
	 *
	 * @return bool
	 */
	public function sendForTranslation ( $contentType, $sourceBlog, $sourceEntity, $targetBlog, $targetEntity = null ) {
		$submission = $this->prepareSubmissionEntity( $contentType, $sourceBlog, $sourceEntity, $targetBlog,
			$targetEntity );

		return $this->sendForTranslationBySubmission( $submission );
	}

	/**
	 * @param string   $contentType
	 * @param int      $sourceBlog
	 * @param int      $sourceEntity
	 * @param int      $targetBlog
	 * @param int|null $targetEntity
	 *
	 * @return bool
	 */
	public function createForTranslation (
		$contentType,
		$sourceBlog,
		$sourceEntity,
		$targetBlog,
		$targetEntity = null
	) {
		$submission = $this->prepareSubmissionEntity( $contentType, $sourceBlog, $sourceEntity, $targetBlog,
			$targetEntity );

		$contentEntity = $this->readContentEntity( $submission );

		if ( null === $submission->getId() ) {
			$submission->setSourceContentHash( $contentEntity->calculateHash() );
			$submission->setSourceTitle( $contentEntity->getTitle() );

			// generate URI
			$submission->getFileUri();
			$submission = $this->getSubmissionManager()->storeEntity( $submission );
		}

		return $this->getSubmissionManager()->storeEntity( $submission );
	}

	/**
	 * Sends data to smartling directly
	 *
	 * @param SubmissionEntity $submission
	 * @param string           $xmlFileContent
	 *
	 * @return bool
	 */
	protected function sendStream ( SubmissionEntity $submission, $xmlFileContent ) {
		return $this->getApiWrapper()->uploadContent( $submission, $xmlFileContent );
	}

	/**
	 * Sends data to smartling via temporary file
	 *
	 * @param SubmissionEntity $submission
	 * @param string           $xmlFileContent
	 *
	 * @return bool
	 */
	protected function sendFile ( SubmissionEntity $submission, $xmlFileContent ) {
		$tmp_file = tempnam( sys_get_temp_dir(), '_smartling_temp_' );

		file_put_contents( $tmp_file, $xmlFileContent );

		$result = $this->getApiWrapper()->uploadContent( $submission, '', $tmp_file );

		unlink( $tmp_file );

		return $result;
	}

	public function downloadTranslationBySubmission ( SubmissionEntity $entity ) {
		$messages = array ();

		try {
			$data = $this->getApiWrapper()->downloadFile( $entity );

			$translatableFields = $this->getTranslatableFields( $entity->getContentType() );

			$translatedFields = XmlEncoder::xmlDecode( $translatableFields, $data, $this->getProcessorFactory() );

			$targetId = (int) $entity->getTargetId();

			$targetContent = null;

			if ( 0 === $targetId ) {
				// need to clone original content first.
				$originalEntity = $this->readContentEntity( $entity );

				$targetContent = clone $originalEntity;

				$targetContent->cleanFields();

			} else {
				$targetContent = $this->readTargetContentEntity( $entity );
			}

			$this->setValues( $targetContent, $translatedFields );

			$this->saveEntity( $entity->getContentType(), $entity->getTargetBlogId(), $targetContent );

			$this->saveMetaProperties( $entity->getTargetBlogId(), $targetContent, $translatedFields );

			if ( 0 === $targetId ) {

				$entity->setTargetId( $targetContent->getPK() );

				$entity = $this->getSubmissionManager()->storeEntity( $entity );

				$result = $this->getMultilangProxy()->linkObjects( $entity );
			}

			if ( 100 === $entity->getCompletionPercentage() ) {
				$entity->setStatus( SubmissionEntity::SUBMISSION_STATUS_COMPLETED );
			}

			$entity->appliedDate = DateTimeHelper::nowAsString();

			$entity = $this->getSubmissionManager()->storeEntity( $entity );
		} catch ( Exception $e ) {
			$messages[] = $e->getMessage();
		}

		return $messages;
	}

	private function saveEntity ( $type, $blog, EntityAbstract $entity ) {
		$curBlogId = $this->getSiteHelper()->getCurrentBlogId();

		if ( $blog !== $curBlogId ) {
			$this->getSiteHelper()->switchBlogId( $blog );
		}

		$ioWrapper = $this->getContentIoFactory()->getMapper( $type );

		$id = $ioWrapper->set( $entity );

		$PkField = $entity->getPrimaryFieldName();

		$entity->$PkField = $id;

		if ( $blog !== $curBlogId ) {
			$this->getSiteHelper()->restoreBlogId();
		}

		return $entity;
	}

	/**
	 * @param int                  $blogId
	 * @param EntityAbstract       $entity
	 * @param PropertyDescriptor[] $properties
	 *
	 * @return EntityAbstract
	 */
	private function saveMetaProperties ( $blogId, EntityAbstract $entity, array $properties ) {
		$curBlogId = $this->getSiteHelper()->getCurrentBlogId();

		if ( $blogId !== $curBlogId ) {
			$this->getSiteHelper()->switchBlogId( $blogId );
		}

		foreach ( $properties as $property ) {
			if ( $property->isMeta() ) {
				$entity->setMetaTag( $property->getName(), $property->getValue() );
			}
		}

		if ( $blogId !== $curBlogId ) {
			$this->getSiteHelper()->restoreBlogId();
		}
	}

	/**
	 * @param EntityAbstract       $entity
	 * @param PropertyDescriptor[] $properties
	 */
	private function setValues ( EntityAbstract $entity, array $properties ) {
		foreach ( $properties as $property ) {
			if ( ! $property->isMeta() ) {
				$entity->{$property->getName()} = $property->getValue();
			}
		}
	}

	public function downloadTranslationBySubmissionId ( $id ) {
		return $this->downloadTranslationBySubmission( $this->loadSubmissionEntityById( $id ) );
	}

	public function downloadTranslation (
		$contentType,
		$sourceBlog,
		$sourceEntity,
		$targetBlog,
		$targetEntity = null
	) {
		$submission = $this->prepareSubmissionEntity( $contentType, $sourceBlog, $sourceEntity, $targetBlog,
			$targetEntity );

		return $this->downloadTranslationBySubmission( $submission );

	}

	/**
	 * Cuts off all non-translatable fields from array that represents the content entity
	 *
	 * @param array $entity
	 * @param array $translatableFields
	 *
	 * @return array
	 */
	private function cutOffFields ( array $entity, array $translatableFields ) {
		return array_intersect_key( $entity, array_flip( $translatableFields ) );
	}

	/**
	 * @param $contentType
	 *
	 * @return PropertyDescriptor[]
	 */
	private function getTranslatableFields ( $contentType ) {
		return $this->settings->getMapper()->getMapper( $contentType )->getFields();
	}

	private function getContentIOWrapper ( SubmissionEntity $entity ) {
		return $this->getContentIoFactory()->getMapper( $entity->getContentType() );
	}

	/**
	 * @param SubmissionEntity $entity
	 *
	 * @return EntityAbstract
	 */
	private function readContentEntity ( SubmissionEntity $entity ) {

		$contentIOWrapper = $this->getContentIOWrapper( $entity );

		if ( $this->getSiteHelper()->getCurrentBlogId() === $entity->getSourceBlogId() ) {
			$contentEntity = $contentIOWrapper->get( $entity->getSourceId() );
		} else {
			$this->getSiteHelper()->switchBlogId( $entity->getSourceBlogId() );
			$contentEntity = $contentIOWrapper->get( $entity->getSourceId() );
			$this->getSiteHelper()->restoreBlogId();
		}

		return $contentEntity;
	}

	private function readTargetContentEntity ( SubmissionEntity $entity ) {

		$contentIOWrapper = $this->getContentIOWrapper( $entity );

		if ( $this->getSiteHelper()->getCurrentBlogId() === $entity->getTargetBlogId() ) {
			$contentEntity = $contentIOWrapper->get( $entity->getTargetId() );
		} else {
			$this->getSiteHelper()->switchBlogId( $entity->getTargetBlogId() );
			$contentEntity = $contentIOWrapper->get( $entity->getTargetId() );
			$this->getSiteHelper()->restoreBlogId();
		}

		return $contentEntity;
	}

	/**
	 * Checks and updates submission with given ID
	 *
	 * @param $id
	 *
	 * @return array of error messages
	 */
	public function checkSubmissionById ( $id ) {
		$messages = array ();

		try {
			$submission = $this->loadSubmissionEntityById( $id );

			$this->checkSubmissionByEntity( $submission );
		} catch ( SmartlingExceptionAbstract $e ) {
			$messages[] = $e->getMessage();
		} catch ( Exception $e ) {
			$messages[] = $e->getMessage();
		}

		return $messages;
	}

	/**
	 * Checks and updates given submission entity
	 *
	 * @param SubmissionEntity $submission
	 *
	 * @return array of error messages
	 */
	public function checkSubmissionByEntity ( SubmissionEntity $submission ) {
		$messages = array ();

		try {
			$submission = $this->getApiWrapper()->getStatus( $submission );

			$submission = $this->getSubmissionManager()->storeEntity( $submission );
		} catch ( SmartlingExceptionAbstract $e ) {
			$messages[] = $e->getMessage();
		} catch ( Exception $e ) {
			$messages[] = $e->getMessage();
		}

		return $messages;
	}

	/**
	 * @param $id
	 *
	 * @return mixed
	 * @throws SmartlingDbException
	 */
	private function loadSubmissionEntityById ( $id ) {
		$params = array (
			'id' => $id,
		);

		$entities = $this->getSubmissionManager()->find( $params );

		if ( count( $entities ) > 0 ) {
			return reset( $entities );
		} else {
			$message = vsprintf( 'Requested SubmissionEntity with id=%s does not exist.', array ( $id ) );

			$this->getLogger()->error( $message );
			throw new SmartlingDbException( $message );
		}
	}

	/**
	 * @param string   $contentType
	 * @param int      $sourceBlog
	 * @param mixed    $sourceEntity
	 * @param int      $targetBlog
	 * @param int|null $targetEntity
	 *
	 * @return SubmissionEntity
	 */
	private function prepareSubmissionEntity (
		$contentType,
		$sourceBlog,
		$sourceEntity,
		$targetBlog,
		$targetEntity = null
	) {
		return $this->getSubmissionManager()->getSubmissionEntity( $contentType, $sourceBlog, $sourceEntity,
			$targetBlog, $this->getMultilangProxy(), $targetEntity );
	}

	/**
	 * @param SubmissionEntity $entity
	 */
	public function checkEntityForDownload ( SubmissionEntity $entity ) {
		if ( 100 === $entity->getCompletionPercentage() ) {
			$this->downloadTranslationBySubmission( $entity );
		}
	}

	public function bulkCheckNewAndInProgress () {
		$entities = $this->getSubmissionManager()->find( array (
				'status' => array (
					SubmissionEntity::SUBMISSION_STATUS_NEW,
					SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS,
				)
			)
		);

		foreach ( $entities as $entity ) {
			if ( $entity->getStatus() === SubmissionEntity::SUBMISSION_STATUS_NEW ) {
				$this->sendForTranslationBySubmission( $entity );
			}
			if ( $entity->getStatus() === SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS ) {
				$this->checkSubmissionByEntity( $entity );
				$this->checkEntityForDownload( $entity );
			}
		}
	}

	/**
	 * @param array $items
	 *
	 * @return array
	 * @throws SmartlingDbException
	 */
	public function bulkCheckByIds ( array $items ) {
		$results = array ();
		foreach ( $items as $item ) {
			/** @var SubmissionEntity $entity */
			$entity = $this->loadSubmissionEntityById( $item );
			if ( $entity->getStatus() == SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS ) {
				$this->checkSubmissionByEntity( $entity );
				$this->checkEntityForDownload( $entity );
				$results[] = $entity;
			}
		}

		return $results;
	}

	/**
	 * @param ConfigurationProfileEntity $profile
	 *
	 * @return array
	 */
	public function getProjectLocales ( ConfigurationProfileEntity $profile ) {
		return $this->getApiWrapper()->getSupportedLocales( $profile );
	}
}