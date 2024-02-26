<?php

namespace Smartling\Base;

use JetBrains\PhpStorm\ArrayShape;
use Smartling\Exception\SmartlingWpDataIntegrityException;
use Smartling\Helpers\AttachmentHelper;
use Smartling\Helpers\ContentHelper;
use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Helpers\SiteHelper;
use Smartling\Helpers\StringHelper;
use Smartling\Helpers\TranslationHelper;
use Smartling\Jobs\JobEntityWithBatchUid;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;

class SmartlingCoreAttachments
{
    use LoggerSafeTrait;

    public function __construct(
        private ContentHelper $contentHelper,
        private SettingsManager $settingsManager,
        private SiteHelper $siteHelper,
        private TranslationHelper $translationHelper,
    ) {
        add_action(ExportedAPI::ACTION_SMARTLING_REGENERATE_THUMBNAILS, [$this, 'regenerateTargetThumbnailsBySubmission']);
        add_action(ExportedAPI::ACTION_SMARTLING_SYNC_MEDIA_ATTACHMENT, [$this, 'syncAttachment']);
    }

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

        $this->getLogger()->debug(sprintf('Starting syncing media attachment blogId=%d attachmentId=%d', $submission->getTargetBlogId(), $submission->getTargetId()));
        $profile = $this->settingsManager->getSingleSettingsProfile($submission->getSourceBlogId());
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
        $this->getLogger()->debug(sprintf('Preparing to sync media file for blogId=%d attachmentId=%d', $submission->getTargetBlogId(), $submission->getTargetId()));

        $fileData = $this->getAttachmentFileInfoBySubmission($submission);

        $sourceFileFsPath = $fileData['source_path_prefix'] . DIRECTORY_SEPARATOR . $fileData['relative_path'];
        $targetFileFsPath = $fileData['target_path_prefix'] . DIRECTORY_SEPARATOR . $fileData['relative_path'];

        $mediaCloneResult = AttachmentHelper::cloneFile($sourceFileFsPath, $targetFileFsPath, true);

        if (is_array($mediaCloneResult) && 0 < count($mediaCloneResult)) {
            $message = sprintf(
                'Error%s %s happened while working with attachmentId=%d, blogId=%d, submissionId=%d',
                count($mediaCloneResult) > 1 ? 's' : '',
                implode(',', $mediaCloneResult),
                $submission->getSourceId(),
                $submission->getSourceBlogId(),
                $submission->getId(),
            );
            $this->getLogger()->error($message);
        }
    }

    /**
     * Forces image thumbnail re-generation
     */
    public function regenerateTargetThumbnailsBySubmission(SubmissionEntity $submission): void
    {
        $this->getLogger()->debug(
            vsprintf('Starting thumbnails regeneration for blog = \'%s\' attachment id = \'%s\'.', [
                $submission->getTargetBlogId(),
                $submission->getTargetId(),
            ])
        );

        $this->siteHelper->withBlog($submission->getTargetBlogId(), function () use ($submission) {
            $originalImage = get_attached_file($submission->getTargetId(), true);

            if (!function_exists('wp_generate_attachment_metadata')) {
                include_once(ABSPATH . 'wp-admin/includes/image.php'); //including the attachment function
            }
            if (!function_exists('wp_read_video_metadata')) {
                include_once(ABSPATH . 'wp-admin/includes/media.php');
            }

            $this->getLogger()->debug(sprintf('Generating metadata for file targetId=%d, [%s].', $submission->getTargetId(), $originalImage));

            $metadata = wp_generate_attachment_metadata($submission->getTargetId(), $originalImage);

            if (empty($metadata)) {
                $this->getLogger()->error(sprintf("Couldn't regenerate thumbnails for targetBlogId=%d attachmentId=%d", $submission->getTargetBlogId(), $submission->getTargetId()));
            } else {
                wp_update_attachment_metadata($submission->getTargetId(), $metadata);
            }
        });
    }

    private function getUploadDirForSite(int $siteId): array
    {
        return $this->siteHelper->withBlog($siteId, function () {
            return wp_upload_dir();
        });
    }

    public function getUploadPathForSite(int $siteId): string
    {
        $prefix = $this->getUploadDirForSite($siteId);
        return str_replace($prefix['subdir'], '', parse_url($prefix['url'], PHP_URL_PATH));
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
        $info = $this->contentHelper->readSourceContent($submission);
        if (property_exists($info, 'guid')) {
            $guid = $info->guid;
        } else {
            $guid = '';
            $this->getLogger()->debug('Tried to get attachment file info for ' . get_class($info));
        }
        $sourceSiteUploadInfo = $this->getUploadDirForSite($submission->getSourceBlogId());
        $targetSiteUploadInfo = $this->getUploadDirForSite($submission->getTargetBlogId());
        $sourceMetadata = $this->contentHelper->readSourceMetadata($submission);

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

    public function getFullyRelateAttachmentPath(SubmissionEntity $postSubmission, string $foundRelativePath): string
    {
        return $this->getFullyRelateAttachmentPathByBlogId($postSubmission->getSourceBlogId(), $foundRelativePath);
    }

    public function getFullyRelateAttachmentPathByBlogId(int $blogId, string $foundRelativePath): string
    {
        return trim(str_replace($this->getUploadPathForSite($blogId), '', $foundRelativePath), '/');
    }

    public function sendAttachmentForTranslation(int $sourceBlogId, int $targetBlogId, int $sourceId, JobEntityWithBatchUid $jobInfo, bool $clone = false): SubmissionEntity
    {
        return $this->translationHelper->tryPrepareRelatedContent(
            'attachment',
            $sourceBlogId,
            $sourceId,
            $targetBlogId,
            $jobInfo,
            $clone
        );
    }

    /**
     * @throws SmartlingWpDataIntegrityException
     */
    public function getAttachmentRelativePathBySubmission(SubmissionEntity $submission): string
    {
        $info = $this->getAttachmentFileInfoBySubmission($submission);

        return parse_url($info['base_url_target'] . '/' . $info['relative_path'], PHP_URL_PATH);
    }

    /**
     * @throws SmartlingWpDataIntegrityException
     */
    public function getAttachmentAbsolutePathBySubmission(SubmissionEntity $submission): string
    {
        $info = $this->getAttachmentFileInfoBySubmission($submission);

        return $info['base_url_target'] . '/' . $info['relative_path'];
    }

    public function getUploadFileInfo(int $siteId): array
    {
        return $this->getUploadDirForSite($siteId);
    }
}
