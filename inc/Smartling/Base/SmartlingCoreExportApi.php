<?php

namespace Smartling\Base;

use Smartling\Bootstrap;
use Smartling\Helpers\WordpressContentTypeHelper;
use Smartling\Submissions\SubmissionEntity;

trait SmartlingCoreExportApi {
	/**
	 * @param SubmissionEntity $postSubmission
	 * @param                  $foundRelativePath
	 *
	 * @return mixed
	 */
	public function getFullyRelateAttachmentPath ( SubmissionEntity $postSubmission, $foundRelativePath ) {
		$prefix = $this->getUploadPathForSite( $postSubmission->getSourceBlogId() );

		$fullyRelativePath = trim( str_replace( $prefix, '', $foundRelativePath ), '/' );

		return $fullyRelativePath;
	}

	/**
	 * @param int $sourceBlogId
	 * @param int $targetBlogId
	 * @param int $sourceId
	 *
	 * @return SubmissionEntity
	 */
	public function sendAttachmentForTranslation ( $sourceBlogId, $targetBlogId, $sourceId ) {
		$submission = $this->fastSendForTranslation(
			WordpressContentTypeHelper::CONTENT_TYPE_MEDIA_ATTACHMENT,
			$sourceBlogId,
			$sourceId,
			$targetBlogId
		);

		return $submission;
	}

	public function getAttachmentRelativePathBySubmission ( SubmissionEntity $submission ) {
		$info = $this->getAttachmentFileInfoBySubmission( $submission );

		$absoluteUrl = $info['base_url_target'] . '/' . $info['relative_path'];

		$relativePath = parse_url( $absoluteUrl, PHP_URL_PATH );

		return $relativePath;
	}
}