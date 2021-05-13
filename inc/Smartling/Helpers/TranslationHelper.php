<?php

namespace Smartling\Helpers;

use Smartling\Jobs\JobEntityWithBatchUid;
use Smartling\Services\GlobalSettingsManager;
use UnexpectedValueException;
use Psr\Log\LoggerInterface;
use Smartling\Base\ExportedAPI;
use Smartling\Bootstrap;
use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\Exception\SmartlingDataReadException;
use Smartling\MonologWrapper\MonologWrapper;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

class TranslationHelper
{
    private LocalizationPluginProxyInterface $multilangProxy;
    private LoggerInterface $logger;
    private SiteHelper $siteHelper;
    private SubmissionManager $submissionManager;

    public function __construct(LocalizationPluginProxyInterface $proxy, SiteHelper $siteHelper, SubmissionManager $submissionManager) {
        $this->logger = MonologWrapper::getLogger(get_called_class());
        $this->multilangProxy = $proxy;
        $this->siteHelper = $siteHelper;
        $this->submissionManager = $submissionManager;
    }

    /**
     * @throws UnexpectedValueException
     */
    private function validateBlogs(int $sourceBlogId, int $targetBlogId): void
    {
        $blogs = $this->siteHelper->listBlogIdsFlat();

        if (!in_array($sourceBlogId, $blogs, true)) {
            $exception = new UnexpectedValueException(
                vsprintf('Unexpected value: sourceBlogId must be one of [%s], %s got',
                    [implode(', ',$blogs), $sourceBlogId])
            );

            $this->logger->warning(
                vsprintf(
                    'Trying to get/create submission with invalid sourceBlogId, trace:\n\n%s\nRequest dump:\n%s',
                    [
                        $exception->getTraceAsString(),
                        base64_encode(serialize(Bootstrap::getRequestContext())),
                    ]
                )
            );
            throw $exception;
        }

        if (!in_array($targetBlogId, $blogs, true)) {
            $exception = new UnexpectedValueException(
                vsprintf('Unexpected value: targetBlogId must be one of [%s], %s got',
                    [implode(', ', $blogs), $targetBlogId])
            );

            $this->logger->warning(
                vsprintf(
                    'Trying to get/create submission with invalid targetBlogId, trace:\n\n%s\nRequest dump:\n%s',
                    [
                        $exception->getTraceAsString(),
                        base64_encode(serialize(Bootstrap::getRequestContext())),
                    ]
                )
            );
            throw $exception;
        }

        if ($sourceBlogId === $targetBlogId) {
            $exception = new UnexpectedValueException('Unexpected value: sourceBlogId cannot be same as targetBlogId');

            $this->logger->warning(
                vsprintf(
                    'Trying to get/create submission with same sourceBlogId and targetBlogId, trace:\n\n%s\nRequest dump:\n%s',
                    [
                        $exception->getTraceAsString(),
                        base64_encode(serialize(Bootstrap::getRequestContext())),
                    ]
                )
            );
            throw $exception;
        }
    }

    public function prepareSubmissionEntity(string $contentType, int $sourceBlog, int $sourceEntity, int $targetBlog, ?int $targetEntity = null): SubmissionEntity
    {
        $this->validateBlogs($sourceBlog, $targetBlog);

        return $this->submissionManager->getSubmissionEntity(
            $contentType,
            $sourceBlog,
            $sourceEntity,
            $targetBlog,
            $this->multilangProxy,
            $targetEntity
        );
    }

    /**
     * @throws SmartlingDataReadException
     */
    public function prepareSubmission(string $contentType, int $sourceBlog, int $sourceId, int $targetBlog, bool $clone = false): SubmissionEntity
    {
        if (0 === $sourceId) {
            throw new \InvalidArgumentException('Source id cannot be 0.');
        }
        $submission = $this->prepareSubmissionEntity(
            $contentType,
            $sourceBlog,
            $sourceId,
            $targetBlog
        );

        if (0 === (int)$submission->getId()) {
            if (GlobalSettingsManager::isHandleRelationsManually()) {
                $this->logger->debug(sprintf('Created submission %s %d despite manual relations handling). Backtrace: %s', $submission->getContentType(), $submission->getSourceId(), json_encode(debug_backtrace())));
            }

            if (true === $clone) {
                $submission->setIsCloned(1);
            }
            $submission = $this->submissionManager->storeEntity($submission);
        }

        return $this->reloadSubmission($submission);
    }

    /**
     * @throws SmartlingDataReadException
     */
    public function reloadSubmission(SubmissionEntity $submission): SubmissionEntity
    {
        $submissionsList = $this->submissionManager->getEntityById($submission->getId());
        if (is_array($submissionsList)) {
            return ArrayHelper::first($submissionsList);
        }
        $message = vsprintf(
            'Error while reloading submission. Nothing returned from database. Original Submission: %s',
            [var_export($submission->toArray(false), true),]
        );
        throw new SmartlingDataReadException($message);
    }

    public function isRelatedSubmissionCreationNeeded(string $contentType, int $sourceBlogId, int $contentId, int $targetBlogId): bool {
        return !GlobalSettingsManager::isHandleRelationsManually() ||
            $this->submissionManager->submissionExistsNoLastError($contentType, $sourceBlogId, $contentId, $targetBlogId);
    }

    /**
     * @throws SmartlingDataReadException
     */
    public function getExistingSubmissionOrCreateNew(string $contentType, int $sourceBlogId, int $contentId, int $targetBlogId, JobEntityWithBatchUid $jobInfo): SubmissionEntity {
        $submission = $this->submissionManager->getSubmissionEntity($contentType, $sourceBlogId, $contentId, $targetBlogId, $this->multilangProxy);
        if ($submission->getTargetId() === 0) {
            $this->logger->debug("Got submission with 0 target id");
            $submission = $this->tryPrepareRelatedContent($contentType, $sourceBlogId, $contentId, $targetBlogId, $jobInfo);
        }
        return $submission;
    }

    /**
     * @throws SmartlingDataReadException
     */
    public function tryPrepareRelatedContent(string $contentType, int $sourceBlog, int $sourceId, int $targetBlog, JobEntityWithBatchUid $jobInfo, bool $clone = false): SubmissionEntity
    {
        $relatedSubmission = $this->prepareSubmission($contentType, $sourceBlog, $sourceId, $targetBlog, $clone);

        if (0 !== $sourceId && 0 === $relatedSubmission->getTargetId() &&
            SubmissionEntity::SUBMISSION_STATUS_FAILED !== $relatedSubmission->getStatus()
        ) {
            $message = vsprintf(
                'Trying to create corresponding translation placeholder for submission=%s for contentType=%s sourceBlog=%s sourceId=%s, targetBlog=%s',
                [
                    $relatedSubmission->getId(),
                    $contentType,
                    $sourceBlog,
                    $sourceId,
                    $targetBlog,
                ]
            );

            $relatedSubmission->setBatchUid($jobInfo->getBatchUid());
            $relatedSubmission->setJobInfo($jobInfo->getJobInformationEntity());
            $serialized = $relatedSubmission->toArray(false);
            if (null === $serialized[SubmissionEntity::FIELD_FILE_URI]) {
                $relatedSubmission->getFileUri();
            }
            $relatedSubmission = $this->submissionManager->storeEntity($relatedSubmission);
            // try to create target entity
            $relatedSubmission = apply_filters(ExportedAPI::FILTER_SMARTLING_PREPARE_TARGET_CONTENT, $relatedSubmission);

            // add to upload queue
            $relatedSubmission->setStatus(SubmissionEntity::SUBMISSION_STATUS_NEW);
            $relatedSubmission = $this->submissionManager->storeEntity($relatedSubmission);
        } else {
            $message = vsprintf(
                'Skipping creation of translation placeholder for submission=%s, locale=%s.',
                [
                    $relatedSubmission->getId(),
                    $relatedSubmission->getTargetLocale(),
                ]
            );
        }

        $this->logger->debug($message);

        return $this->reloadSubmission($relatedSubmission);
    }
}
