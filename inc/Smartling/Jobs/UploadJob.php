<?php

namespace Smartling\Jobs;

use Smartling\ApiWrapperInterface;
use Smartling\Base\ExportedAPI;
use Smartling\DbAl\UploadQueueManager;
use Smartling\Exception\SmartlingDbException;
use Smartling\Helpers\Cache;
use Smartling\Helpers\FileUriHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;
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

        $this->processCloning($blogId);

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
            }
            if ($submission->getFileUri() === '') {
                $submission->setFileUri($this->fileUriHelper->generateFileUri($submission));
                $this->submissionManager->storeEntity($submission);
            }
            if (!array_key_exists($submission->getSourceBlogId(), $profiles)) {
                try {
                    $profiles[$submission->getSourceBlogId()] = $this->settingsManager->getSingleSettingsProfile($submission->getSourceBlogId());
                } catch (SmartlingDbException) {
                    $message = "No active profile found for blogId={$submission->getSourceBlogId()}";
                    foreach ($item->getSubmissions() as $itemSubmission) {
                        $this->getLogger()->notice("Skipping upload of submissionId={$itemSubmission->getId()}: $message");
                        $this->submissionManager->setErrorMessage($itemSubmission, $message);
                    }
                    $this->uploadQueueManager->complete($item);
                    continue;
                }
            }
            $profile = $profiles[$submission->getSourceBlogId()];
            if ($item->getBatchUid() === '') {
                try {
                    $item = $item->setBatchUid($this->api->getOrCreateJobInfoForDailyBucketJob($profile, [$submission->getFileUri()])->getBatchUid());
                } catch (\Throwable $e) {
                    foreach ($item->getSubmissions() as $itemSubmission) {
                        $this->getLogger()->notice("Skipping upload of submissionId={$itemSubmission->getId()}: failed to get or create daily bucket job: {$e->getMessage()}");
                        $this->submissionManager->setErrorMessage($itemSubmission, $e->getMessage());
                    }
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
                foreach ($item->getSubmissions() as $submission) {
                    $this->getLogger()->notice(sprintf('Failing submissionId=%s: %s', $submission->getId(), $e->getMessage()));
                    $this->submissionManager->setErrorMessage($submission, $e->getMessage());
                }
            }
            $this->uploadQueueManager->complete($item);
            $this->placeLockFlag(true);
        }
    }

    private function processCloning(int $blogId): void
    {
        while (($submission = $this->submissionManager->findSubmissionForCloning($blogId)) !== null) {
            try {
                $this->wpProxy->do_action(ExportedAPI::ACTION_SMARTLING_CLONE_CONTENT, $submission);
            } catch (\Throwable $e) {
                $this->submissionManager->setErrorMessage($submission, $e->getMessage());
                continue;
            }
            $this->placeLockFlag(true);
        }
    }
}
