<?php
namespace Smartling\Base;

use Exception;

use Smartling\DbAl\WordpressContentEntities\MenuItemEntity;
use Smartling\Exception\SmartlingDbException;
use Smartling\Exception\SmartlingExceptionAbstract;
use Smartling\Helpers\WordpressContentTypeHelper;
use Smartling\Helpers\XmlEncoder;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Submissions\SubmissionEntity;

/**
 * Class SmartlingCore
 *
 * @package Smartling\Base
 */
class SmartlingCore extends SmartlingCoreAbstract {

	use SmartlingCoreTrait;

	/**
	 * current mode to send data to Smartling
	 */
	const SEND_MODE = self::SEND_MODE_FILE;

	/**
	 * @param SubmissionEntity $submission
	 */
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
					$terms = $this->getCustomMenuHelper()->getTerms( $submission, $contentType );

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

					$ids = $this
						->getCustomMenuHelper()
						->getMenuItems(
							$submission->getSourceId(),
							$submission->getSourceBlogId()
						);

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

						$data['meta']['_menu_item_object_id'] =
							reset( $menuItem->getMetadata()['_menu_item_object_id'] );

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

			$this->getCustomMenuHelper()->assignMenuItemsToMenu(
				(int) $submission->getTargetId(),
				(int) $submission->getTargetBlogId(),
				$accumulator[ WordpressContentTypeHelper::CONTENT_TYPE_NAV_MENU ]
			);
		}


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

	private function getContentIOWrapper ( SubmissionEntity $entity ) {
		return $this->getContentIoFactory()->getMapper( $entity->getContentType() );
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