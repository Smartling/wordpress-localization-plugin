<?php

namespace Smartling\Jobs;

use Smartling\Base\ExportedAPI;
use Smartling\Helpers\ArrayHelper;
use Smartling\Queue\Queue;

/**
 * Class DownloadTranslationJob
 * @package Smartling\Jobs
 */
class DownloadTranslationJob extends JobAbstract
{
    const JOB_HOOK_NAME = 'smartling-download-task';

    /**
     * @var Queue
     */
    private $queue;

    /**
     * @return Queue
     */
    public function getQueue()
    {
        return $this->queue;
    }

    /**
     * @param Queue $queue
     */
    public function setQueue($queue)
    {
        $this->queue = $queue;
    }

    public function getJobHookName(): string
    {
        return self::JOB_HOOK_NAME;
    }

    public function run(): void
    {
        $this->getLogger()->info('Started Translation Download Job.');

        $this->processDownloadQueue();

        $this->getLogger()->info('Finished Translation Download Job.');
    }

    private function processDownloadQueue()
    {
        while (false !== ($submissionId = $this->getQueue()->dequeue(Queue::QUEUE_NAME_DOWNLOAD_QUEUE))) {
            $submissionId = ArrayHelper::first($submissionId);
            $result = $this->getSubmissionManager()->find(['id' => $submissionId]);

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
