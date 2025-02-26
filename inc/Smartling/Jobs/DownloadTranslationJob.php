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
    public const string JOB_HOOK_NAME = 'smartling-download-task';

    public function __construct(
        ApiWrapperInterface $api,
        Cache $cache,
        SettingsManager $settingsManager,
        SubmissionManager $submissionManager,
        int $throttleIntervalSeconds,
        string $jobRunInterval,
        int $workerTTL,
        private QueueInterface $queue
    ) {
        parent::__construct($api, $cache, $settingsManager, $submissionManager, $throttleIntervalSeconds, $jobRunInterval, $workerTTL);
    }

    public function getJobHookName(): string
    {
        return self::JOB_HOOK_NAME;
    }

    public function run(string $source): void
    {
        $this->getLogger()->debug('Started Translation Download Job.');

        $this->processDownloadQueue();

        $this->getLogger()->debug('Finished Translation Download Job.');
    }

    private function processDownloadQueue(): void
    {
        while (null !== ($submissionId = $this->queue->dequeue(QueueInterface::QUEUE_NAME_DOWNLOAD_QUEUE))) {
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
