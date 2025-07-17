<?php

namespace Smartling\Jobs;

use Smartling\ApiWrapperInterface;
use Smartling\Base\ExportedAPI;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\Cache;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Queue\QueueInterface;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionManager;

class DownloadTranslationJob extends JobAbstract
{
    public const JOB_HOOK_NAME = 'smartling-download-task';

    public function __construct(
        ApiWrapperInterface $api,
        Cache $cache,
        SettingsManager $settingsManager,
        SubmissionManager $submissionManager,
        private WordpressFunctionProxyHelper $wpProxy,
        int $throttleIntervalSeconds,
        string $jobRunInterval,
        private QueueInterface $queue
    ) {
        parent::__construct($api, $cache, $settingsManager, $submissionManager, $throttleIntervalSeconds, $jobRunInterval);
    }

    public function getJobHookName(): string
    {
        return self::JOB_HOOK_NAME;
    }

    public function run(string $source): void
    {
        $this->getLogger()->debug('Started Translation Download Job.');

        $this->processDownloadQueue($this->wpProxy->get_current_blog_id());

        $this->getLogger()->debug('Finished Translation Download Job.');
    }

    private function processDownloadQueue(int $blogId): void
    {
        $processed = 0;
        while ($processed++ < ($this->queue->stats()[QueueInterface::QUEUE_NAME_DOWNLOAD_QUEUE] ?? 0)) {
            $queueItem = $this->queue->dequeue(QueueInterface::QUEUE_NAME_DOWNLOAD_QUEUE);
            if ($queueItem === null) {
                break;
            }
            $submissionId = ArrayHelper::first($queueItem);
            $entity = $this->submissionManager->getEntityById($submissionId);

            if ($entity === null) {
                $this->getLogger()
                    ->warning(vsprintf('Got submission id=%s that does not exists in database. Skipping.', [$submissionId]));
                continue;
            }
            if ($entity->getSourceBlogId() !== $blogId) {
                $this->queue->enqueue($queueItem, QueueInterface::QUEUE_NAME_DOWNLOAD_QUEUE);
                continue;
            }

            do_action(ExportedAPI::ACTION_SMARTLING_DOWNLOAD_TRANSLATION, $entity);
            $this->placeLockFlag(true);
        }
    }
}
