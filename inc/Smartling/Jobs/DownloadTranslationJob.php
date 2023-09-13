<?php

namespace Smartling\Jobs;

use Smartling\ApiWrapperInterface;
use Smartling\Base\ExportedAPI;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\Cache;
use Smartling\Queue\QueueInterface;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionManager;

class DownloadTranslationJob extends JobAbstract
{
    public const JOB_HOOK_NAME = 'smartling-download-task';

    private QueueInterface $queue;

    public function __construct(
        ApiWrapperInterface $api,
        Cache $cache,
        SettingsManager $settingsManager,
        SubmissionManager $submissionManager,
        string $jobRunInterval,
        int $workerTTL,
        QueueInterface $queue
    ) {
        parent::__construct($api, $cache, $settingsManager, $submissionManager, $jobRunInterval, $workerTTL);
        $this->queue = $queue;
    }

    public function getJobHookName(): string
    {
        return self::JOB_HOOK_NAME;
    }

    public function run(): void
    {
        $this->getLogger()->debug('Started Translation Download Job.');

        $this->processDownloadQueue();

        $this->getLogger()->debug('Finished Translation Download Job.');
    }

    private function processDownloadQueue(): void
    {
        while (false !== ($submissionId = $this->queue->dequeue(QueueInterface::QUEUE_NAME_DOWNLOAD_QUEUE))) {
            $submissionId = ArrayHelper::first($submissionId);
            $result = $this->submissionManager->find(['id' => $submissionId]);

            if (0 < count($result)) {
                $entity = ArrayHelper::first($result);
            } else {
                $this->getLogger()
                    ->warning(vsprintf('Got submission id=%s that does not exists in database. Skipping.', [$submissionId]));
                continue;
            }

            do_action(ExportedAPI::ACTION_SMARTLING_DOWNLOAD_TRANSLATION, $entity);
            $this->placeLockFlag(true);
        }
    }
}
