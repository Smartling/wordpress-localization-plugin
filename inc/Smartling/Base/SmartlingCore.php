<?php
namespace Smartling\Base;

use Exception;
use Psr\Log\LoggerInterface;

use Smartling\ApiWrapperInterface;
use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\DbAl\WordpressContentEntities\EntityAbstract;
use Smartling\DbAl\WordpressContentEntities\MenuItemEntity;
use Smartling\Exception\InvalidXMLException;
use Smartling\Exception\SmartlingDbException;
use Smartling\Exception\SmartlingExceptionAbstract;
use Smartling\Helpers\Cache;
use Smartling\Helpers\DateTimeHelper;
use Smartling\Helpers\SiteHelper;
use Smartling\Helpers\WordpressContentTypeHelper;
use Smartling\Helpers\XmlEncoder;
use Smartling\Processors\ContentEntitiesIOFactory;
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
	 * @var Cache
	 */
	private $cache;

	/**
	 * @return Cache
	 */
	public function getCache () {
		return $this->cache;
	}

	/**
	 * @param Cache $cache
	 */
	public function setCache ( Cache $cache ) {
		$this->cache = $cache;
	}

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

	private function fastSendForTranslation ( $contentType, $sourceBlog, $sourceId, $targetBlog ) {
		$relatedSubmission = $this->prepareSubmissionEntity(
			$contentType,
			$sourceBlog,
			$sourceId,
			$targetBlog
		);

		if ( 0 === (int) $relatedSubmission->getId() ) {
			$relatedSubmission = $this->getSubmissionManager()->storeEntity( $relatedSubmission );
		}

		$submission_id = $relatedSubmission->getId();

		$this->sendForTranslationBySubmission( $relatedSubmission );

		$lst = $this->getSubmissionManager()->getEntityById( $submission_id );

		$relatedSubmission = reset( $lst );

		return $relatedSubmission;
	}

	/**
	 * Sends Entity for translation and returns ID of linked entity in target blog
	 *
	 * @param string $contentType
	 * @param int    $sourceBlog
	 * @param int    $sourceId
	 * @param int    $targetBlog
	 *
	 * @return int
	 */
	private function translateAndGetTargetId ( $contentType, $sourceBlog, $sourceId, $targetBlog ) {
		$submission = $this->fastSendForTranslation( $contentType, $sourceBlog, $sourceId, $targetBlog );

		return (int) $submission->getTargetId();
	}

	/**
	 * @param int $menuId
	 * @param int $blogId
	 *
	 * @return MenuItemEntity[]
	 */
	private function getMenuItems ( $menuId, $blogId ) {
		$options = [
			'order'                  => 'ASC',
			'orderby'                => 'menu_order',
			'post_type'              => WordpressContentTypeHelper::CONTENT_TYPE_NAV_MENU_ITEM,
			'post_status'            => 'publish',
			'output'                 => ARRAY_A,
			'output_key'             => 'menu_order',
			'nopaging'               => true,
			'update_post_term_cache' => false,
		];

		$needBlogSwitch = $this->getSiteHelper()->getCurrentBlogId() !== $blogId;

		if ( $needBlogSwitch ) {
			$this->getSiteHelper()->switchBlogId( $blogId );
		}

		$items = wp_get_nav_menu_items( $menuId, $options );

		$ids = [ ];

		$mapper = $this
			->getContentIoFactory()
			->getMapper( WordpressContentTypeHelper::CONTENT_TYPE_NAV_MENU_ITEM );
		foreach ( $items as $item ) {
			$m     = clone $mapper;
			$ids[] = $m->get( (int) $item->ID );;
		}

		if ( $needBlogSwitch ) {
			$this->getSiteHelper()->restoreBlogId();
		}

		return $ids;
	}

	/**
	 * @param SubmissionEntity $submission
	 * @param string           $taxonomy
	 *
	 * @return array
	 */
	private function getTerms ( $submission, $taxonomy ) {
		$needBlogSwitch = $submission->getSourceBlogId() !== $this->getSiteHelper()->getCurrentBlogId();

		if ( $needBlogSwitch ) {
			$this->getSiteHelper()->switchBlogId( $submission->getSourceBlogId() );
		}

		$terms = wp_get_object_terms( $submission->getSourceId(), $taxonomy );

		if ( $needBlogSwitch ) {
			$this->getSiteHelper()->restoreBlogId();
		}

		return is_array( $terms ) ? $terms : [ ];
	}

	public function prepareRelatedSubmissions ( SubmissionEntity $submission ) {
		$originalEntity      = $this->readContentEntity( $submission );
		$relatedContentTypes = $originalEntity->getRelatedTypes();
		$termList            = WordpressContentTypeHelper::getSupportedTaxonomyTypes();
		$accumulator         = [
			WordpressContentTypeHelper::CONTENT_TYPE_CATEGORY => [ ],
			WordpressContentTypeHelper::CONTENT_TYPE_POST_TAG => [ ],
		];

		if ( ! empty( $relatedContentTypes ) ) {

			foreach ( $relatedContentTypes as $contentType ) {
				if ( in_array( $contentType, $termList ) ) {
					$terms = $this->getTerms( $submission, $contentType );

					if ( 0 < count( $terms ) ) {
						foreach ( $terms as $element ) {
							$accumulator[ $contentType ][] = $this->translateAndGetTargetId(
								$element->taxonomy,
								$submission->getSourceBlogId(),
								$element->term_id,
								$submission->getTargetBlogId() );
						}
					}
				} elseif ( WordpressContentTypeHelper::CONTENT_TYPE_NAV_MENU_ITEM === $contentType ) {

					$ids = $this->getMenuItems( $submission->getSourceId(), $submission->getSourceBlogId() );

					/** @var MenuItemEntity $menuItem */
					foreach ( $ids as $menuItem ) {

						$needBlogSwitch = $submission->getSourceBlogId() !== $this->getSiteHelper()->getCurrentBlogId();

						if ( $needBlogSwitch ) {
							$this->getSiteHelper()->switchBlogId( $submission->getSourceBlogId() );
						}

						$data = XmlEncoder::xmlDecode(
							XmlEncoder::xmlEncode(
								[
									'entity' => $menuItem->toArray(),
									'meta'   => $menuItem->getMetadata(),
								]
							)
						);

						if ( $needBlogSwitch ) {
							$this->getSiteHelper()->restoreBlogId();
						}

						$relatedSubmission = null;
						$objectId          = 0;
						switch ( $data['meta']['_menu_item_type'] ) {
							case 'taxonomy':
							case 'post_type': {
								$objectId = $this->translateAndGetTargetId(
									$data['meta']['_menu_item_object'],
									$submission->getSourceBlogId(),
									$data['meta']['_menu_item_object_id'],
									$submission->getTargetBlogId()
								);
								break;
							}
							case 'custom': {
								break;
							}
						}

						$relatedSubmission = $this->fastSendForTranslation(
							WordpressContentTypeHelper::CONTENT_TYPE_NAV_MENU_ITEM,
							$submission->getSourceBlogId(),
							$menuItem->getPK(),
							$submission->getTargetBlogId()
						);


						$targetContent = $this->readTargetContentEntity( $relatedSubmission );

						$needBlogSwitch = $submission->getTargetBlogId() !== $this->getSiteHelper()->getCurrentBlogId();

						if ( $needBlogSwitch ) {
							$this->getSiteHelper()->switchBlogId( $submission->getTargetBlogId() );
						}

						$targetContent->setMetaTag( '_menu_item_object_id', $objectId );

						if ( $needBlogSwitch ) {
							$this->getSiteHelper()->restoreBlogId();
						}

						$accumulator[ WordpressContentTypeHelper::CONTENT_TYPE_NAV_MENU ][] = $targetContent->getPK();
						unset ( $targetContent, $relatedSubmission );
					}
				}
			}
		}

		if ( $submission->getContentType() !== WordpressContentTypeHelper::CONTENT_TYPE_NAV_MENU ) {
			$this->getSiteHelper()->switchBlogId( $submission->getTargetBlogId() );

			foreach ( $accumulator as $type => $ids ) {
				wp_set_post_terms( $submission->getTargetId(), $ids, $type );
			}

			$this->getSiteHelper()->restoreBlogId();
		} else {

			$this->assignMenuItemsToMenu(
				(int) $submission->getTargetId(),
				(int) $submission->getTargetBlogId(),
				$accumulator[ WordpressContentTypeHelper::CONTENT_TYPE_NAV_MENU ]
			);
		}


	}


	/**
	 * @param int   $menuId
	 * @param int   $blogId
	 * @param int[] $items
	 */
	private function assignMenuItemsToMenu ( $menuId, $blogId, $items ) {
		$needBlogChange = $blogId !== $this->getSiteHelper()->getCurrentBlogId();

		if ( $needBlogChange ) {
			$this->getSiteHelper()->switchBlogId( $blogId );
		}

		foreach ( $items as $item ) {
			wp_set_object_terms( $item, [ (int) $menuId ], WordpressContentTypeHelper::CONTENT_TYPE_NAV_MENU );
		}

		if ( $needBlogChange ) {
			$this->getSiteHelper()->restoreBlogId();
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

		$source = [
			'entity' => $contentEntity->toArray(),
			'meta'   => $contentEntity->getMetadata(),
		];

		$source['meta'] = $source['meta'] ? : [ ];


		$xml = XmlEncoder::xmlEncode( $source );

		$submission = $this->prepareTargetEntity( $submission );

		$this->prepareRelatedSubmissions( $submission );

		$result = false;

		if ( empty( $source['entity'] ) && empty( $source['meta'] ) ) {
			$this->getLogger()->error( vsprintf( 'Nothing to translate for submission #%s',
				[ $submission->getId() ] ) );
			$submission->setStatus( SubmissionEntity::SUBMISSION_STATUS_FAILED );
		} else {
			try {
				$result = self::SEND_MODE === self::SEND_MODE_FILE
					? $this->sendFile( $submission, $xml )
					: $this->sendStream( $submission, $xml );

				$submission->setStatus( SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS );

			} catch ( Exception $e ) {
				$this->getLogger()->error( $e->getMessage() );
				$submission->setStatus( SubmissionEntity::SUBMISSION_STATUS_FAILED );
			}
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
		} else {
			$submission->setStatus( SubmissionEntity::SUBMISSION_STATUS_NEW );
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

	/**
	 * Prepares a duplicate of source content for target site and links them.
	 * To be used JUST BEFORE SENDING to Smartling
	 *
	 * @param SubmissionEntity $submission
	 *
	 * @return SubmissionEntity
	 */
	protected function prepareTargetEntity ( SubmissionEntity $submission ) {
		$update = 0 !== (int) $submission->getTargetId();

		$originalContent = $this->readContentEntity( $submission );

		$original = XmlEncoder::xmlDecode(
			XmlEncoder::xmlEncode(
				[
					'entity' => $originalContent->toArray(),
					'meta'   => $originalContent->getMetadata(),
				]
			)
		);

		if ( false === $update ) {
			$targetContent = clone $originalContent;
			$targetContent->cleanFields();
		} else {
			$targetContent = $this->readTargetContentEntity( $submission );
		}

		unset ( $original['entity']['ID'], $original['entity']['term_id'] );

		foreach ( $original['entity'] as $k => $v ) {
			$targetContent->{$k} = $v;
		}

		$targetContent = $this->saveEntity(
			$submission->getContentType(),
			$submission->getTargetBlogId(),
			$targetContent
		);

		if ( array_key_exists( 'meta', $original ) && 0 < count( $original['meta'] ) ) {
			$this->saveMetaProperties(
				$targetContent,
				$original,
				$submission
			);
		}

		if ( false === $update ) {
			$submission->setTargetId( $targetContent->getPK() );
			$submission = $this->getSubmissionManager()->storeEntity( $submission );
		}

		$result = $this->getMultilangProxy()->linkObjects( $submission );

		return $submission;
	}

	public function downloadTranslationBySubmission ( SubmissionEntity $entity ) {

		if ( 1 === $entity->getIsLocked() ) {
			$msg = vsprintf( 'Triggered download of locked entity. Target Blog: %s; Target Id: %s', [
				$entity->getTargetBlogId(),
				$entity->getTargetId(),
			] );

			$this->getLogger()->warning( $msg );

			return [ 'Translation is locked for downloading' ];
		}

		$messages = [ ];

		try {

			// detect old (ver < 24) submissions and fix them
			if (0 === (int) $entity->getTargetId())
			{
				$entity = $this->prepareTargetEntity( $entity );
			}

			$data = $this->getApiWrapper()->downloadFile( $entity );

			$translatedFields = XmlEncoder::xmlDecode( $data );

			$targetId = (int) $entity->getTargetId();

			$targetContent = $this->readTargetContentEntity( $entity );

			$this->setValues( $targetContent, $translatedFields['entity'] );

			$targetContent = $this->saveEntity( $entity->getContentType(), $entity->getTargetBlogId(), $targetContent );

			if ( array_key_exists( 'meta', $translatedFields ) && 0 < count( $translatedFields['meta'] ) ) {
				$this->saveMetaProperties( $targetContent, $translatedFields, $entity );
			}

			if ( 100 === $entity->getCompletionPercentage() ) {
				$entity->setStatus( SubmissionEntity::SUBMISSION_STATUS_COMPLETED );
			}

			$entity->appliedDate = DateTimeHelper::nowAsString();

			$entity = $this->getSubmissionManager()->storeEntity( $entity );
		} catch ( InvalidXMLException $e ) {
			$entity->setStatus( SubmissionEntity::SUBMISSION_STATUS_FAILED );
			$this->getSubmissionManager()->storeEntity( $entity );

			$message = vsprintf( "Invalid XML file [%s] received. Submission moved to %s status.",
				[
					$entity->getFileUri(),
					$entity->getStatus(),
				] );

			$this->getLogger()->error( $message );
			$messages[] = $message;
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

	private function saveMetaProperties ( EntityAbstract $entity, array $properties, SubmissionEntity $submission ) {
		$curBlogId = $this->getSiteHelper()->getCurrentBlogId();

		if ( $submission->getTargetBlogId() !== $curBlogId ) {
			$this->getSiteHelper()->switchBlogId( $submission->getTargetBlogId() );
		}

		if ( array_key_exists( 'meta', $properties ) && $properties['meta'] !== '' ) {
			$metaFields = &$properties['meta'];

			foreach ( $metaFields as $metaName => $metaValue ) {
				if ( '' === $metaValue ) {
					continue;
				}
				$entity->setMetaTag( $metaName, $metaValue );
			}
		}

		if ( $submission->getTargetBlogId() !== $curBlogId ) {
			$this->getSiteHelper()->restoreBlogId();
		}
	}

	/**
	 * @param EntityAbstract $entity
	 * @param array          $properties
	 */
	private function setValues ( EntityAbstract $entity, array $properties ) {
		foreach ( $properties as $propertyName => $propertyValue ) {
			$entity->{$propertyName} = $propertyValue;
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
		$needBlogSwitch = $this->getSiteHelper()->getCurrentBlogId() !== $entity->getTargetBlogId();

		if ( $needBlogSwitch ) {
			$this->getSiteHelper()->switchBlogId( $entity->getTargetBlogId() );
		}

		$wrapper = $this->getContentIoFactory()->getMapper( $entity->getContentType() );

		$entity = $wrapper->get( $entity->getTargetId() );

		if ( $needBlogSwitch ) {
			$this->getSiteHelper()->restoreBlogId();
		}

		return $entity;
	}

	/**
	 * Checks and updates submission with given ID
	 *
	 * @param $id
	 *
	 * @return array of error messages
	 */
	public function checkSubmissionById ( $id ) {
		$messages = [ ];

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
		$messages = [ ];

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
		$params = [
			'id' => $id,
		];

		$entities = $this->getSubmissionManager()->find( $params );

		if ( count( $entities ) > 0 ) {
			return reset( $entities );
		} else {
			$message = vsprintf( 'Requested SubmissionEntity with id=%s does not exist.', [ $id ] );

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
		$entities = $this->getSubmissionManager()->find( [
				'status' => [
					SubmissionEntity::SUBMISSION_STATUS_NEW,
					SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS,
				],
			]
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
		$results = [ ];
		foreach ( $items as $item ) {
			/** @var SubmissionEntity $entity */
			try {
				$entity = $this->loadSubmissionEntityById( $item );
			} catch ( SmartlingDbException $e ) {
				$this->getLogger()->error( 'Requested submission that does not exist: ' . (int) $item );
				continue;
			}
			if ( $entity->getStatus() === SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS ) {
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
		$cacheKey = 'profile.locales.' . $profile->getId();
		$cached   = $this->getCache()->get( $cacheKey );

		if ( false === $cached ) {
			$cached = $this->getApiWrapper()->getSupportedLocales( $profile );
			$this->getCache()->set( $cacheKey, $cached );
		}

		return $cached;
	}
}