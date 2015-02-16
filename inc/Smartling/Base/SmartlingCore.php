<?php
namespace Smartling\Base;

use DateTime;
use Exception;
use Psr\Log\LoggerInterface;

use Smartling\ApiWrapperInterface;
use Smartling\Bootstrap;
use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\DbAl\WordpressContentEntities\EntityAbstract;
use Smartling\Exception\SmartlingDbException;
use Smartling\Exception\SmartlingException;
use Smartling\Helpers\DateTimeHelper;
use Smartling\Helpers\SiteHelper;
use Smartling\Helpers\WordpressUserHelper;
use Smartling\Helpers\XmlEncoder;
use Smartling\Processors\ContentEntitiesIOFactory;
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
	 * @var SubmissionEntity
	 */
	private $lastSubmissionEntity;

	/**
	 * @var ContentEntitiesIOFactory
	 */
	private $contentIoFactory;

	/**
	 * @return SubmissionEntity
	 */
	public function getLastSubmissionEntity () {
		return $this->lastSubmissionEntity;
	}

	/**
	 * @var LocalizationPluginProxyInterface
	 */
	private $multilangProxy;

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
	public function setMultilangProxy ( LocalizationPluginProxyInterface $multilangProxy ) {
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

	private function linkObjectsBySubmission ( SubmissionEntity $entity ) {
		return $this->getMultilangProxy()->linkObjects( $entity );
	}

	public function sendForTranslationBySubmissionId ( $id ) {
		return $this->sendForTranslationBySubmission( $this->prepareSubmissionEntityById( $id ) );
	}

	public function sendForTranslationBySubmission ( SubmissionEntity $submission ) {
		$contentEntity = $this->readContentEntity( $submission );


		if ( null === $submission->getId() ) {
			$submission->setSourceContentHash( $contentEntity->calculateHash() );

			$submission->setSourceTitle( $contentEntity->getTitle() );

			// generate URI
			$submission->getFileUri();

			$submission = $this->getSubmissionManager()->storeEntity( $submission );


		}

		$translatableFields = $this->getTranslatableFields( $submission->getContentType() );

		$dataForConversion = $this->cutOffFields( $contentEntity->toArray(), $translatableFields );

		$xml = XmlEncoder::xmlEncode( $dataForConversion );

		$result = false;

		try {
			$result = self::SEND_MODE === self::SEND_MODE_FILE
				? $this->sendFile( $submission, $xml )
				: $this->sendStream( $submission, $xml );

			if ( true === $result ) {
				$submission->setStatus( SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS );
			} else {
				$submission->setStatus( SubmissionEntity::SUBMISSION_STATUS_FAILED );
			}
		} catch ( SmartlingException $e ) {
			$submission->setStatus( SubmissionEntity::SUBMISSION_STATUS_FAILED );
		} catch ( Exception $e ) {
			$submission->setStatus( SubmissionEntity::SUBMISSION_STATUS_FAILED );
		}

		$submission = $this->getSubmissionManager()->storeEntity( $submission );

		$this->lastSubmissionEntity = $submission;

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
		$data = $this->getApiWrapper()->downloadFile( $entity );

		$translatableFields = $this->getTranslatableFields( $entity->getContentType() );

		$structure = XmlEncoder::xmlDecode( $translatableFields, $data );

		$targetId = (int) $entity->getTargetGUID();

		$targetContent = null;

		if ( 0 === $targetId ) {
			// need to clone original content first.
			$originalEntity = $this->readContentEntity( $entity );

			$targetContent = clone $originalEntity;

			$targetContent->cleanFields();

			$this->setValues( $targetContent, $structure );

		} else {
			$targetContent = $this->readTargetContentEntity( $entity );
		}

		$this->saveEntity( $entity->getContentType(), $entity->getTargetBlog(), $targetContent );

		if ( 0 === $targetId ) {

			$entity->setTargetGUID( $targetContent->getPK() );

			$entity = $this->getSubmissionManager()->storeEntity( $entity );

			$this->linkObjectsBySubmission( $entity );
		}

		$this->lastSubmissionEntity = $entity;
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

	private function setValues ( EntityAbstract $entity, array $fields ) {
		foreach ( $fields as $field => $value ) {
			$entity->$field = $value;
		}
	}

	public function downloadTranslationBySubmissionId ( $id ) {
		return $this->downloadTranslationBySubmission( $this->prepareSubmissionEntityById( $id ) );
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
	 * @return mixed
	 */
	private function getTranslatableFields ( $contentType ) {
		return $this->settings->getMapperWrapper()->getMapper( $contentType )->getFields();
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

		if ( $this->getSiteHelper()->getCurrentBlogId() === $entity->getSourceBlog() ) {
			$contentEntity = $contentIOWrapper->get( $entity->getSourceGUID() );
		} else {
			$this->getSiteHelper()->switchBlogId( $entity->getSourceBlog() );
			$contentEntity = $contentIOWrapper->get( $entity->getSourceGUID() );
			$this->getSiteHelper()->restoreBlogId();
		}

		return $contentEntity;
	}

	private function readTargetContentEntity ( SubmissionEntity $entity ) {

		$contentIOWrapper = $this->getContentIOWrapper( $entity );

		if ( $this->getSiteHelper()->getCurrentBlogId() === $entity->getTargetBlog() ) {
			$contentEntity = $contentIOWrapper->get( $entity->getTargetGUID() );
		} else {
			$this->getSiteHelper()->switchBlogId( $entity->getTargetBlog() );
			$contentEntity = $contentIOWrapper->get( $entity->getTargetGUID() );
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
			$submission = $this->prepareSubmissionEntityById( $id );

			$submission = $this->getApiWrapper()->getStatus( $submission );

			$submission = $this->getSubmissionManager()->storeEntity( $submission );
		} catch ( SmartlingException $e ) {
			$messages[] = $e->getMessage();
		} catch ( Exception $e ) {
			$messages[] = $e->getMessage();
		}

		return $messages;
	}


	private function prepareSubmissionEntityById ( $id ) {
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

		$entity = null;

		$params = array (
			'contentType' => $contentType,
			'sourceBlog'  => $sourceBlog,
			'sourceGUID'  => $sourceEntity,
			'targetBlog'  => $targetBlog,
		);

		if ( null !== $targetEntity ) {
			$params['targetGUID'] = $targetEntity;
		}

		$entities = $this->getSubmissionManager()->find( $params );

		if ( count( $entities ) > 0 ) {
			$entity = reset( $entities );
		} else {
			$entity = $this->getSubmissionManager()->createSubmission( $params );
			$entity->setTargetLocale(
				$this->getMultilangProxy()->getBlogLocaleById(
					$entity->getTargetBlog()
				)
			);
			$entity->setStatus( SubmissionEntity::SUBMISSION_STATUS_NEW );
			$entity->setSubmitter( WordpressUserHelper::getUserLogin() );
			$entity->setSourceTitle( '' );
			$entity->setSubmissionDate( DateTimeHelper::dateTimeToString( new DateTime() ) );
		}

		return $entity;
	}


}