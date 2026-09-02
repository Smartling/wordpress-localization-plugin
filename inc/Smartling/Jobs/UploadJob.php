<?php

namespace Smartling\Jobs;

use Smartling\ApiWrapperInterface;
use Smartling\Base\ExportedAPI;
use Smartling\DbAl\UploadQueueManager;
use Smartling\Exception\SmartlingDbException;
use Smartling\Helpers\Cache;
use Smartling\Helpers\FileUriHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Models\UploadQueueItem;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionManager;

class UploadJob extends JobAbstract
{
    public const JOB_HOOK_NAME = 'smartling-upload-task';

    public function __construct(
        ApiWrapperInterface $api,
        Cache $cache,
        private FileUriHelper $fileUriHelper,
        SettingsManager $settingsManager,
        SubmissionManager $submissionManager,
        private UploadQueueManager $uploadQueueManager,
        private WordpressFunctionProxyHelper $wpProxy,
        int $throttleIntervalSeconds,
        string $jobRunInterval,
    ) {
        parent::__construct($api, $cache, $settingsManager, $submissionManager, $throttleIntervalSeconds, $jobRunInterval);
    }

    public function getJobHookName(): string
    {
        return self::JOB_HOOK_NAME;
    }

    /**
     * The upload queue claims rows with a compare-and-swap (see UploadQueueManager::claim()),
     * so concurrent runs of this job can no longer double-process the same item. The
     * account-level distributed lock is no longer needed for correctness here.
     */
    protected function usesDistributedLock(): bool
    {
        return false;
    }

    public function run(string $source): void
    {
        $message = 'UploadJob';
        if ($source !== '') {
            $message .= ", source=\"$source\"";
        }
        $blogId = $this->wpProxy->get_current_blog_id();
        $message .= ", blogId=$blogId";
        $this->getLogger()->debug("Started $message");

        $this->processUploadQueue($blogId);

        $this->getLogger()->debug("Finished $message");
    }

    private function processUploadQueue(int $blogId): void
    {
        $processed = 0;
        $profiles = [];
        $max = $this->uploadQueueManager->length();
        while ($processed++ < $max) {
            $item = $this->uploadQueueManager->dequeue($blogId);
            if ($item === null) {
                break;
            }
            $submission = $item->getSubmissions()[0];
            $this->getLogger()->debug("Retrieved upload queue item for submissionId={$submission->getId()}");
            if ($submission->isCloned()) {
                $this->getLogger()->debug("Skipping processing queue for submissionId={$submission->getId()}: was cloned");
                $this->uploadQueueManager->complete($item);
                continue;
            }
            if ($submission->getFileUri() === '') {
                $submission->setFileUri($this->fileUriHelper->generateFileUri($submission));
                $this->submissionManager->storeEntity($submission);
            }
            if (!array_key_exists($submission->getSourceBlogId(), $profiles)) {
                try {
                    $profiles[$submission->getSourceBlogId()] = $this->settingsManager->getSingleSettingsProfile($submission->getSourceBlogId());
                } catch (SmartlingDbException) {
                    $this->failItem($item, 'Skipping upload of', "No active profile found for blogId={$submission->getSourceBlogId()}");
                    $this->uploadQueueManager->complete($item);
                    continue;
                }
            }
            $profile = $profiles[$submission->getSourceBlogId()];
            if ($item->getBatchUid() === '') {
                try {
                    $item = $item->setBatchUid($this->api->getOrCreateJobInfoForDailyBucketJob($profile, [$submission->getFileUri()])->getBatchUid());
                } catch (\Throwable $e) {
                    $this->failItem($item, 'Skipping upload of', $e->getMessage(), "failed to get or create daily bucket job: {$e->getMessage()}");
                    $this->uploadQueueManager->complete($item);
                    continue;
                }
            }

            $this->getLogger()->info(sprintf(
                'Cron Job upload for submissionId="%s" with status="%s" contentType="%s", sourceBlogId="%s", contentId="%s", targetBlogId="%s", targetLocale="%s", batchUid="%s"',
                $submission->getId(),
                $submission->getStatus(),
                $submission->getContentType(),
                $submission->getSourceBlogId(),
                $submission->getSourceId(),
                $submission->getTargetBlogId(),
                $submission->getTargetLocale(),
                $item->getBatchUid(),
            ));

            try {
                $this->wpProxy->do_action(ExportedAPI::ACTION_SMARTLING_SEND_FOR_TRANSLATION, $item);
            } catch (\Throwable $e) {
                $this->failItem($item, 'Failing', $e->getMessage());
            }
            $this->uploadQueueManager->complete($item);
            $this->placeLockFlag(true);
        }
    }

    private function failItem(UploadQueueItem $item, string $logVerb, string $errorMessage, ?string $logMessage = null): void
    {
        $logMessage ??= $errorMessage;
        foreach ($item->getSubmissions() as $submission) {
            $this->getLogger()->notice("$logVerb submissionId={$submission->getId()}: $logMessage");
            $this->submissionManager->setErrorMessage($submission, $errorMessage);
        }
    }
}
