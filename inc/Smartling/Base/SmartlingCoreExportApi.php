<?php

namespace Smartling\Base;

use Smartling\Submissions\SubmissionEntity;

trait SmartlingCoreExportApi
{
    /**
     * @param SubmissionEntity $postSubmission
     * @param string $foundRelativePath
     * @return string
     */
    public function getFullyRelateAttachmentPath(SubmissionEntity $postSubmission, $foundRelativePath)
    {
        return $this->getFullyRelateAttachmentPathByBlogId($postSubmission->getSourceBlogId(), $foundRelativePath);
    }

    /**
     * @param int $blogId
     * @param string $foundRelativePath
     * @return string
     */
    public function getFullyRelateAttachmentPathByBlogId($blogId, $foundRelativePath)
    {
        return trim(str_replace($this->getUploadPathForSite($blogId), '', $foundRelativePath), '/');
    }

    /**
     * @param int    $sourceBlogId
     * @param int    $targetBlogId
     * @param int    $sourceId
     * @param string $batchUid
     * @param bool   $clone
     *
     * @return SubmissionEntity
     */
    public function sendAttachmentForTranslation($sourceBlogId, $targetBlogId, $sourceId, $batchUid, $clone = false)
    {
        return $this->getTranslationHelper()->tryPrepareRelatedContent(
            'attachment',
            $sourceBlogId,
            $sourceId,
            $targetBlogId,
            $batchUid,
            $clone
        );
    }

    /**
     * @param SubmissionEntity $submission
     * @return string
     */
    public function getAttachmentRelativePathBySubmission(SubmissionEntity $submission)
    {
        $info = $this->getAttachmentFileInfoBySubmission($submission);

        return parse_url($info['base_url_target'] . '/' . $info['relative_path'], PHP_URL_PATH);
    }

    /**
     * @param SubmissionEntity $submission
     * @return string
     */
    public function getAttachmentAbsolutePathBySubmission(SubmissionEntity $submission)
    {
        $info = $this->getAttachmentFileInfoBySubmission($submission);

        return $info['base_url_target'] . '/' . $info['relative_path'];
    }

    /**
     * @param int $siteId
     * @return array
     */
    public function getUploadFileInfo($siteId)
    {
        return $this->getUploadDirForSite($siteId);
    }
}
