<?php
namespace Smartling\Base;

use Psr\Log\LoggerInterface;

use Smartling\ApiWrapperInterface;
use Smartling\Bootstrap;
use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\DbAl\WordpressContentEntities\EntityAbstract;
use Smartling\Helpers\SiteHelper;
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

		$contentEntity = $this->readContentEntity( $submission );

		$submission->setSourceContentHash($contentEntity->calculateHash());

		$submission->setSourceTitle($contentEntity->getTitle());

		$submission = $this->getSubmissionManager()->storeEntity($submission);

		$translatableFields = $this->getTranslatableFields( $submission->getContentType() );

		$dataForConversion = $this->cutOffFields( $contentEntity->toArray(), $translatableFields );

		$xml = XmlEncoder::xmlEncode( $dataForConversion );

		$result = $this->getApiWrapper()->uploadFile( $submission, $xml );

		$this->lastSubmissionEntity = $submission;

		return $result;
	}

	public function downloadTranslation( $contentType, $sourceBlog, $sourceEntity, $targetBlog, $targetEntity = null)
	{
		$submission = $this->prepareSubmissionEntity( $contentType, $sourceBlog, $sourceEntity, $targetBlog,
			$targetEntity );

		$xmlString = $this->getApiWrapper()->downloadFile($submission);

		$translatableFields = $this->getTranslatableFields( $submission->getContentType() );

		$data = XmlEncoder::xmlDecode($translatableFields, $xmlString);

		if (null !== $submission->getTargetGUID()) {
			// update
		} else {
			// create
		}
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

	/**
	 * @param SubmissionEntity $entity
	 *
	 * @return EntityAbstract
	 */
	private function readContentEntity ( SubmissionEntity $entity ) {
		$di = Bootstrap::getContainer();

		/**
		 * @var EntityAbstract $contentIOWrapper
		 */
		$contentIOWrapper = $di->get( 'factory.contentIO' )->getMapper( $entity->getContentType() );

		if ( $this->getSiteHelper()->getCurrentBlogId() === $entity->getSourceBlog() ) {
			$contentEntity = $contentIOWrapper->get( $entity->getSourceGUID() );
		} else {
			$this->getSiteHelper()->switchBlogId( $entity->getSourceBlog() );
			$contentEntity = $contentIOWrapper->get( $entity->getSourceGUID() );
			$this->getSiteHelper()->restoreBlogId();
		}

		return $contentEntity;
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
			$entity->setStatus(SubmissionEntity::SUBMISSION_STATUS_NEW);

			// generate URI
			$entity->getFileUri();

			$entity = $this->getSubmissionManager()->storeEntity( $entity );
		}

		return $entity;
	}
}