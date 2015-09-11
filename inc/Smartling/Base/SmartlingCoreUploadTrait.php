<?php
namespace Smartling\Base;

use Exception;
use Smartling\Helpers\XmlEncoder;
use Smartling\Submissions\SubmissionEntity;

/**
 * Class SmartlingCoreUploadTrait
 *
 * @package Smartling\Base
 */
trait SmartlingCoreUploadTrait {

	public function sendForTranslationBySubmissionId ( $id ) {
		return $this->sendForTranslationBySubmission( $this->loadSubmissionEntityById( $id ) );
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

		if ( false === XmlEncoder::hasStringsForTranslation( $xml ) ) {
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
}