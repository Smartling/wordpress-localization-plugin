<?php

namespace Smartling\Base;

use Smartling\Exception\SmartlingWpDataIntegrityException;
use Smartling\Helpers\AttachmentHelper;
use Smartling\Helpers\StringHelper;
use Smartling\Submissions\SubmissionEntity;

trait SmartlingCoreAttachments
{
    /**
     * @throws SmartlingWpDataIntegrityException
     */
    public function syncAttachment(SubmissionEntity $submission): void
    {
        if ('attachment' !== $submission->getContentType()) {
            return;
        }

        /*
         * Checking if target file exists
         */
        $fileData = $this->getAttachmentFileInfoBySubmission($submission);

        $sourceFileFsPath = $fileData['source_path_prefix'] . DIRECTORY_SEPARATOR . $fileData['relative_path'];
        $targetFileFsPath = $fileData['target_path_prefix'] . DIRECTORY_SEPARATOR . $fileData['relative_path'];

        $targetFileExists = AttachmentHelper::checkIfTargetFileExists($sourceFileFsPath, $targetFileFsPath);

        $this->getLogger()->debug(
            vsprintf('Starting syncing media attachment blog = \'%s\' attachment id = \'%s\'.', [
                $submission->getTargetBlogId(),
                $submission->getTargetId(),
            ])
        );
        $profile = $this->getSettingsManager()->getSingleSettingsProfile($submission->getSourceBlogId());
        if (1 === $profile->getAlwaysSyncImagesOnUpload() || ($submission->getStatus() === SubmissionEntity::SUBMISSION_STATUS_NEW && !$targetFileExists)) {
            $this->syncMediaFile($submission);
        }

        do_action(ExportedAPI::ACTION_SMARTLING_REGENERATE_THUMBNAILS, $submission);
    }

    /**
     * @throws SmartlingWpDataIntegrityException
     */
    private function syncMediaFile(SubmissionEntity $submission): void
    {
        $this->getLogger()->debug(
            vsprintf('Preparing to sync media file for blog = \'%s\' attachment id = \'%s\'.', [
                $submission->getTargetBlogId(),
                $submission->getTargetId(),
            ])
        );

        $fileData = $this->getAttachmentFileInfoBySubmission($submission);


        $sourceFileFsPath = $fileData['source_path_prefix'] . DIRECTORY_SEPARATOR . $fileData['relative_path'];
        $targetFileFsPath = $fileData['target_path_prefix'] . DIRECTORY_SEPARATOR . $fileData['relative_path'];



        $mediaCloneResult = AttachmentHelper::cloneFile($sourceFileFsPath, $targetFileFsPath, true);

        if (is_array($mediaCloneResult) && 0 < count($mediaCloneResult)) {
            $message = vsprintf(
                'Error(s) %s happened while working with attachment id=%s, blog=%s, submission=%s.',
                [
                    implode(',', $mediaCloneResult),
                    $submission->getSourceId(),
                    $submission->getSourceBlogId(),
                    $submission->getId(),
                ]
            );
            $this->getLogger()->error($message);
        }
    }

    /**
     * Forces image thumbnail re-generation
     *
     * @param SubmissionEntity $submission
     */
    public function regenerateTargetThumbnailsBySubmission(SubmissionEntity $submission)
    {
        $this->getLogger()->debug(
            vsprintf('Starting thumbnails regeneration for blog = \'%s\' attachment id = \'%s\'.', [
                $submission->getTargetBlogId(),
                $submission->getTargetId(),
            ])
        );

        $this->getContentHelper()->ensureTargetBlogId($submission);

        $originalImage = get_attached_file($submission->getTargetId(), true);

        if (!function_exists('wp_generate_attachment_metadata')) {
            include_once(ABSPATH . 'wp-admin/includes/image.php'); //including the attachment function
        }

        $this->getLogger()->debug(
            vsprintf('Generating metadata for file id=\'%s\', [%s].', [
                $submission->getTargetId(), $originalImage
            ])
        );

        $metadata = wp_generate_attachment_metadata($submission->getTargetId(), $originalImage);

        if (is_wp_error($metadata)) {

            $this->getLogger()
                ->error(vsprintf('Error occurred while regenerating thumbnails for blog=\'%s\' attachment id=\'%s\'. Message:\'%s\'.', [
                    $submission->getTargetBlogId(),
                    $submission->getTargetId(),
                    $metadata->get_error_message(),
                ]));
            $this->getLogger()
                ->error(var_export($metadata, true));

        }

        if (empty($metadata)) {
            $this->getLogger()
                ->error(vsprintf('Couldn\'t regenerate thumbnails for blog=\'%s\' attachment id=\'%s\'.', [
                    $submission->getTargetBlogId(),
                    $submission->getTargetId(),
                ]));
        } else {


            wp_update_attachment_metadata($submission->getTargetId(), $metadata);

        }


        $this->getContentHelper()->ensureRestoredBlogId();
    }

    /**
     * @param int $siteId
     *
     * @return array
     */
    private function getUploadDirForSite($siteId)
    {
        $this->getContentHelper()->ensureBlog($siteId);
        $data = wp_upload_dir();
        $this->getContentHelper()->ensureRestoredBlogId();

        return $data;
    }

    private function getUploadPathForSite($siteId)
    {
        $this->getContentHelper()->ensureBlog($siteId);
        $prefix = $this->getUploadDirForSite($siteId);
        $data = str_replace($prefix['subdir'], '', parse_url($prefix['url'], PHP_URL_PATH));
        $this->getContentHelper()->ensureRestoredBlogId();

        return $data;
    }

    /**
     * Collects and returns info to copy attachment media
     *
     * @param SubmissionEntity $submission
     *
     * @return array
     * @throws SmartlingWpDataIntegrityException
     */
    private function getAttachmentFileInfoBySubmission(SubmissionEntity $submission)
    {
        $info = $this->getContentHelper()->readSourceContent($submission);
        $sourceSiteUploadInfo = $this->getUploadDirForSite($submission->getSourceBlogId());
        $targetSiteUploadInfo = $this->getUploadDirForSite($submission->getTargetBlogId());
        $sourceMetadata = $this->getContentHelper()->readSourceMetadata($submission);

        if (array_key_exists('_wp_attached_file', $sourceMetadata) &&
            !StringHelper::isNullOrEmpty($sourceMetadata['_wp_attached_file'])
        ) {
            $relativePath = $sourceMetadata['_wp_attached_file'];
            $result = [
                'uri'                => $info->guid,
                'relative_path'      => $relativePath,
                'source_path_prefix' => $sourceSiteUploadInfo['basedir'],
                'target_path_prefix' => $targetSiteUploadInfo['basedir'],
                'base_url_target'    => $targetSiteUploadInfo['baseurl'],
                'filename'           => vsprintf('%s.%s', [
                    pathinfo($relativePath, PATHINFO_FILENAME),
                    pathinfo($relativePath, PATHINFO_EXTENSION),
                ]),
            ];

            return $result;
        }
        throw new SmartlingWpDataIntegrityException(
            vsprintf(
                'Seems like Wordpress has mess in the database, metadata (key=\'%s\') is missing for submission=\'%s\' (content-type=\'%s\', source blog=\'%s\', id=\'%s\')',
                [
                    '_wp_attached_file',
                    $submission->getId(),
                    $submission->getContentType(),
                    $submission->getSourceBlogId(),
                    $submission->getSourceId(),
                ]
            )
        );
    }
}
