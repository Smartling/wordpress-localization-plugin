<?php

namespace Smartling\Base;

use Exception;
use Smartling\Bootstrap;
use Smartling\Exception\BlogNotFoundException;
use Smartling\Exception\EntityNotFoundException;
use Smartling\Exception\InvalidXMLException;
use Smartling\Helpers\DateTimeHelper;
use Smartling\Helpers\EventParameters\AfterDeserializeContentEventParameters;
use Smartling\Helpers\SiteHelper;
use Smartling\Helpers\WordpressContentTypeHelper;
use Smartling\Helpers\XmlEncoder;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

/**
 * Class SmartlingCoreDownloadTrait
 *
 * @package Smartling\Base
 */
trait SmartlingCoreDownloadTrait {

	public function downloadTranslationBySubmission ( SubmissionEntity $entity ) {

		if ( 1 === $entity->getIsLocked() ) {
			$msg = vsprintf( 'Triggered download of locked entity. Target Blog: %s; Target Id: %s', [
				$entity->getTargetBlogId(),
				$entity->getTargetId(),
			] );

			$this->getLogger()->warning( $msg );

			return [
				vsprintf(
					'Translation of file %s for %s locale is locked for downloading',
					[
						$entity->getFileUri(),
						$entity->getTargetLocale(),
					]
				),
			];
		}

		if ( SubmissionEntity::SUBMISSION_STATUS_NEW === $entity->getStatus() ) {
			//Fix for trying to download before send.
			$this->sendForTranslationBySubmission( $entity );
		}

		$messages = [ ];

		try {
			// detect old (ver < 24) submissions and fix them
			if ( 0 === (int) $entity->getTargetId() ) {
				$entity = $this->prepareTargetEntity( $entity );
			}

			$data = $this->getApiWrapper()->downloadFile( $entity );

			$translatedFields = XmlEncoder::xmlDecode( $data );

			if ( ! array_key_exists( 'meta', $translatedFields ) ) {
				$translatedFields['meta'] = [ ];
			}
			$targetId = (int) $entity->getTargetId();

			$targetContent = $this->readTargetContentEntity( $entity );

			$params = new AfterDeserializeContentEventParameters( $translatedFields, $entity, $targetContent,
				$translatedFields['meta'] );

			do_action( XmlEncoder::EVENT_SMARTLING_AFTER_DESERIALIZE_CONTENT, $params );

			$this->setValues( $targetContent, $translatedFields['entity'] );

			$targetContent = $this->saveEntity( $entity->getContentType(), $entity->getTargetBlogId(), $targetContent );

			// $this->fixRelations($entity);

			if ( array_key_exists( 'meta', $translatedFields ) && 0 < count( $translatedFields['meta'] ) ) {
				$this->saveMetaProperties( $targetContent, $translatedFields, $entity );
			}

			if ( 100 === $entity->getCompletionPercentage() ) {
				$entity->setStatus( SubmissionEntity::SUBMISSION_STATUS_COMPLETED );
			}

			$this->prepareRelatedSubmissions( $entity );

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
		} catch ( EntityNotFoundException $e ) {
			$entity->setStatus( SubmissionEntity::SUBMISSION_STATUS_FAILED );
			$this->getLogger()->error( $e->getMessage() );
			$this->getSubmissionManager()->storeEntity( $entity );
		} catch ( BlogNotFoundException $e ) {
			$entity->setStatus( SubmissionEntity::SUBMISSION_STATUS_FAILED );
			$this->getLogger()->error( $e->getMessage() );
			$this->getSubmissionManager()->storeEntity( $entity );
			/** @var SiteHelper $sh */
			$this->handleBadBlogId( $entity );
		} catch ( Exception $e ) {
			$messages[] = $e->getMessage();
		}

		return $messages;
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
}