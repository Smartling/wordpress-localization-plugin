<?php

namespace Smartling\Jobs;

use Smartling\ApiWrapperInterface;
use Smartling\Base\ExportedAPI;
use Smartling\DbAl\UploadQueueManager;
use Smartling\Helpers\Cache;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionManager;

class UploadJob extends JobAbstract
{
    public const JOB_HOOK_NAME = 'smartling-upload-task';

    public function __construct(
        ApiWrapperInterface $api,
        Cache $cache,
        SettingsManager $settingsManager,
        SubmissionManager $submissionManager,
        private UploadQueueManager $uploadQueueManager,
        int $throttleIntervalSeconds,
        string $jobRunInterval,
        int $workerTTL,
    ) {
        parent::__construct($api, $cache, $settingsManager, $submissionManager, $throttleIntervalSeconds, $jobRunInterval, $workerTTL);
    }

    public function getJobHookName(): string
    {
        return self::JOB_HOOK_NAME;
    }

    public function run(): void
    {
        $this->getLogger()->debug('Started UploadJob.');

        $this->processUploadQueue();

        $this->processCloning();

        $this->getLogger()->debug('Finished UploadJob.');
    }

    private function processUploadQueue(): void
    {
        $batchUids = [];
        while (($item = $this->uploadQueueManager->dequeue()) !== null) {
            $submission = $item->getSubmissions()[0];
            $batchUid = $item->getBatchUid();
            if ($batchUid === '') {
                if (!array_key_exists($submission->getSourceBlogId(), $batchUids)) {
                    $profile = null;
                    $profiles = $this->settingsManager->getActiveProfiles();
                    foreach ($profiles as $profile) {
                        if ($profile->getOriginalBlogId()->getBlogId() === $submission->getSourceBlogId()) {
                            break;
                        }
                    }
                    if ($profile === null) {
                        $this->getLogger()->notice("Skipping upload of submissionId={$submission->getId()}: no active profile found for blogId={$submission->getSourceBlogId()}");
                        continue;
                    }
                    $batchUids[$submission->getSourceBlogId()] = $this->api->retrieveJobInfoForDailyBucketJob($profile, [$submission->getFileUri()])->getBatchUid();
                }
                $batchUid = $batchUids[$submission->getSourceBlogId()];
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
                $batchUid,
            ));

            try {
                do_action(ExportedAPI::ACTION_SMARTLING_SEND_FOR_TRANSLATION, $item);
            } catch (\Exception $e) {
                foreach ($item->getSubmissions() as $submission) {
                    $this->getLogger()->notice(sprintf('Failing submissionId=%s: %s', $submission->getId(), $e->getMessage()));
                    $this->submissionManager->setErrorMessage($submission, $e->getMessage());
                }
            }
            $this->placeLockFlag(true);
        }
    }

    private function processCloning(): void
    {
        while (($submission = $this->submissionManager->findSubmissionForCloning()) !== null) {
            do_action(ExportedAPI::ACTION_SMARTLING_CLONE_CONTENT, $submission);
        }
    }
}
