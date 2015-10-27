<?php

namespace Smartling\Base;

use Smartling\Bootstrap;
use Smartling\DbAl\WordpressContentEntities\EntityAbstract;
use Smartling\Helpers\XmlEncoder;
use Smartling\Submissions\SubmissionEntity;

/**
 * Class SmartlingCoreTrait
 *
 * @package Smartling\Base
 */
trait SmartlingCoreTrait {

	use SmartlingCoreUploadTrait;
	use SmartlingCoreDownloadTrait;

	protected function fastSendForTranslation ( $contentType, $sourceBlog, $sourceId, $targetBlog ) {
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
		return $this
			->getSubmissionManager()
			->getSubmissionEntity(
				$contentType,
				$sourceBlog,
				$sourceEntity,
				$targetBlog,
				$this->getMultilangProxy(),
				$targetEntity
			);
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

	/**
	 * @param SubmissionEntity $entity
	 *
	 * @return SubmissionEntity
	 */
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
	 * Prepares a duplicate of source content for target site and links them.
	 * To be used JUST BEFORE SENDING to Smartling
	 *
	 * @param SubmissionEntity $submission
	 *
	 * @return SubmissionEntity
	 */
	protected function prepareTargetEntity ( SubmissionEntity $submission ) {
		$update = 0 !== (int) $submission->getTargetId();

		if ( true === $update ) {
			// do not overwrite existent target content
			return $submission;
		}

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

		unset ( $original['entity']['ID'], $original['entity']['term_id'], $original['entity']['id'] );

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
			if ( $entity->{$propertyName} != $propertyValue ) {
				$message = vsprintf(
					'Replacing field %s with value %s to value %s',
					[
						$propertyName,
						json_encode( $entity->{$propertyName}, JSON_UNESCAPED_UNICODE ),
						json_encode( $propertyValue, JSON_UNESCAPED_UNICODE ),
					]
				);

				$this->getLogger()->debug( $message );
				$entity->{$propertyName} = $propertyValue;
			}
		}
	}

	/**
	 * @param int $siteId
	 *
	 * @return array
	 */
	private function getUploadDirForSite ( $siteId ) {
		$needSiteChange = (int) $siteId !== $this->getSiteHelper()->getCurrentBlogId();

		if ( $needSiteChange ) {
			$this->getSiteHelper()->switchBlogId( (int) $siteId );
		}

		$data = wp_upload_dir();

		if ( $needSiteChange ) {
			$this->getSiteHelper()->restoreBlogId();
		}

		return $data;
	}

	/**
	 * @param SubmissionEntity $submission
	 *
	 * @return array
	 */
	private function getContentEntityMetaBySubmission ( SubmissionEntity $submission ) {

		$contentEntity = $this->readContentEntity( $submission );

		$needSiteChange = $submission->getSourceBlogId() !== $this->getSiteHelper()->getCurrentBlogId();

		if ( $needSiteChange ) {
			$this->getSiteHelper()->switchBlogId( $submission->getSourceBlogId() );
		}

		$metadata = $contentEntity->getMetadata();

		if ( $needSiteChange ) {
			$this->getSiteHelper()->restoreBlogId();
		}

		return $metadata;
	}

	/**
	 * Collects and returns info to copy attachment media
	 *
	 * @param SubmissionEntity $submission
	 *
	 * @return array
	 */
	private function getAttachmentFileInfoBySubmission ( SubmissionEntity $submission ) {

		$info = $this->readContentEntity( $submission );

		$sourceSiteUploadInfo = $this->getUploadDirForSite( $submission->getSourceBlogId() );
		$targetSiteUploadInfo = $this->getUploadDirForSite( $submission->getTargetBlogId() );

		$sourceMetadata = $this->getContentEntityMetaBySubmission( $submission );

		$result = [
			'uri'                => $info->guid,
			'relative_path'      => reset( $sourceMetadata['_wp_attached_file'] ),
			'source_path_prefix' => $sourceSiteUploadInfo['basedir'],
			'target_path_prefix' => $targetSiteUploadInfo['basedir'],
			'base_url_target'    => $targetSiteUploadInfo['baseurl'],
			'filename'           => pathinfo( reset( $sourceMetadata['_wp_attached_file'] ),
					PATHINFO_FILENAME ) . '.' . pathinfo( reset( $sourceMetadata['_wp_attached_file'] ),
					PATHINFO_EXTENSION ),
		];

		return $result;
	}

}