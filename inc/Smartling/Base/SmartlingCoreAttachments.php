<?php

namespace Smartling\Base;

use JetBrains\PhpStorm\ArrayShape;
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
    public function regenerateTargetThumbnailsBySubmission(SubmissionEntity $submission): void
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
        if (!function_exists('wp_read_video_metadata')) {
            include_once(ABSPATH . 'wp-admin/includes/media.php');
        }

        $this->getLogger()->debug(
            vsprintf('Generating metadata for file id=\'%s\', [%s].', [
                $submission->getTargetId(), $originalImage
            ])
        );

        $metadata = wp_generate_attachment_metadata($submission->getTargetId(), $originalImage);

        if (empty($metadata)) {
            $this->getLogger()
                ->error(sprintf("Couldn't regenerate thumbnails for blog='%s' attachment id='%s'.",
                    $submission->getTargetBlogId(),
                    $submission->getTargetId(),
                ));
        } else {
            wp_update_attachment_metadata($submission->getTargetId(), $metadata);
        }

        $this->getContentHelper()->ensureRestoredBlogId();
    }

    private function getUploadDirForSite(int $siteId): array
    {
        $this->getContentHelper()->ensureBlog($siteId);
        $data = wp_upload_dir();
        $this->getContentHelper()->ensureRestoredBlogId();

        return $data;
    }

    /** @noinspection PhpUnusedPrivateMethodInspection
     * @see SmartlingCoreExportApi::getFullyRelateAttachmentPathByBlogId
     */
    private function getUploadPathForSite(int $siteId): string
    {
        $this->getContentHelper()->ensureBlog($siteId);
        $prefix = $this->getUploadDirForSite($siteId);
        $data = str_replace($prefix['subdir'], '', parse_url($prefix['url'], PHP_URL_PATH));
        $this->getContentHelper()->ensureRestoredBlogId();

        return $data;
    }

    #[ArrayShape([
        'uri' => 'string',
        'relative_path' => 'string',
        'source_path_prefix' => 'string',
        'target_path_prefix' => 'string',
        'base_url_target' => 'string',
        'filename' => 'string',
    ])]
    /**
     * Collects and returns info to copy attachment media
     * @throws SmartlingWpDataIntegrityException
     */
    private function getAttachmentFileInfoBySubmission(SubmissionEntity $submission): array
    {
        $info = $this->getContentHelper()->readSourceContent($submission);
        if (property_exists($info, 'guid')) {
            $guid = $info->guid;
        } else {
            $guid = '';
            $this->getLogger()->debug('Tried to get attachment file info for ' . get_class($info));
        }
        $sourceSiteUploadInfo = $this->getUploadDirForSite($submission->getSourceBlogId());
        $targetSiteUploadInfo = $this->getUploadDirForSite($submission->getTargetBlogId());
        $sourceMetadata = $this->getContentHelper()->readSourceMetadata($submission);

        if (array_key_exists('_wp_attached_file', $sourceMetadata) &&
            !StringHelper::isNullOrEmpty($sourceMetadata['_wp_attached_file'])
        ) {
            $relativePath = $sourceMetadata['_wp_attached_file'];

            return [
                'uri' => $guid,
                'relative_path' => $relativePath,
                'source_path_prefix' => $sourceSiteUploadInfo['basedir'],
                'target_path_prefix' => $targetSiteUploadInfo['basedir'],
                'base_url_target' => $targetSiteUploadInfo['baseurl'],
                'filename' => sprintf('%s.%s',
                    pathinfo($relativePath, PATHINFO_FILENAME),
                    pathinfo($relativePath, PATHINFO_EXTENSION),
                ),
            ];
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
