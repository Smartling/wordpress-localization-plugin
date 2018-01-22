<?php

namespace Smartling\Base;

use Smartling\Submissions\SubmissionEntity;

trait SmartlingCoreExportApi
{
    /**
     * @param SubmissionEntity $postSubmission
     * @param                  $foundRelativePath
     *
     * @return mixed
     */
    public function getFullyRelateAttachmentPath(SubmissionEntity $postSubmission, $foundRelativePath)
    {
        $prefix = $this->getUploadPathForSite($postSubmission->getSourceBlogId());

        $fullyRelativePath = trim(str_replace($prefix, '', $foundRelativePath), '/');

        return $fullyRelativePath;
    }

    /**
     * @param int    $sourceBlogId
     * @param int    $targetBlogId
     * @param int    $sourceId
     * @param bool   $clone
     * @param string $batchUid
     *
     * @return SubmissionEntity
     */
    public function sendAttachmentForTranslation($sourceBlogId, $targetBlogId, $sourceId, $clone = false, $batchUid = '')
    {
        $submission = $this->getTranslationHelper()->tryPrepareRelatedContent(
            'attachment',
            $sourceBlogId,
            $sourceId,
            $targetBlogId,
            $clone,
            $batchUid
        );

        return $submission;
    }

    public function getAttachmentRelativePathBySubmission(SubmissionEntity $submission)
    {
        $info = $this->getAttachmentFileInfoBySubmission($submission);

        $absoluteUrl = $info['base_url_target'] . '/' . $info['relative_path'];

        $relativePath = parse_url($absoluteUrl, PHP_URL_PATH);

        return $relativePath;
    }

    public function getAttachmentAbsolutePathBySubmission(SubmissionEntity $submission)
    {
        $info = $this->getAttachmentFileInfoBySubmission($submission);

        $absoluteUrl = $info['base_url_target'] . '/' . $info['relative_path'];

        return $absoluteUrl;
    }

    public function getUploadFileInfo($siteId)
    {
        return $this->getUploadDirForSite($siteId);
    }
}