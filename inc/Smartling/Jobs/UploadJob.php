<?php

namespace Smartling\Jobs;

use Smartling\ApiWrapperInterface;
use Smartling\Base\ExportedAPI;
use Smartling\DbAl\UploadQueueManager;
use Smartling\Helpers\Cache;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;
use Smartling\Vendor\Smartling\Exceptions\SmartlingApiException;

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
        while (($item = $this->uploadQueueManager->dequeue()) !== null) {
            $entity = $this->submissionManager->getEntityById($item->getSubmissionId());
            if ($entity === null) {
                $this->getLogger()->notice('Skipping upload of not-existing submissionId=' . $item->getSubmissionId());
                continue;
            }
            $this->getLogger()->info(sprintf(
                'Cron Job upload for submissionId="%s" with status="%s" contentType="%s", sourceBlogId="%s", contentId="%s", targetBlogId="%s", targetLocale="%s", jobUid="%s"',
                $entity->getId(),
                $entity->getStatus(),
                $entity->getContentType(),
                $entity->getSourceBlogId(),
                $entity->getSourceId(),
                $entity->getTargetBlogId(),
                $entity->getTargetLocale(),
                $item->getJobUid(),
            ));

            try {
                do_action(ExportedAPI::ACTION_SMARTLING_SEND_FILE_FOR_TRANSLATION, $entity, $item->getJobUid());
            } catch (\Exception $e) {
                $this->getLogger()->notice(sprintf('Failing submissionId=%s: %s', $entity->getId(), $e->getMessage()));
                $this->submissionManager->setErrorMessage($entity, $e->getMessage());
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
