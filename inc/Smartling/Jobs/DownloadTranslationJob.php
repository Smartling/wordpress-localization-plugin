<?php

namespace Smartling\Jobs;

use Smartling\Base\ExportedAPI;
use Smartling\Queue\Queue;
use Smartling\Submissions\SubmissionEntity;

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

    /**
     * @return string
     */
    public function getJobHookName()
    {
        return self::JOB_HOOK_NAME;
    }

    /**
     * @return executes job
     */
    public function run()
    {
        $this->getLogger()->info('Started Translation Download Job.');

        $this->processDownloadQueue();

        $this->getLogger()->info('Finished Translation Download Job.');
    }

    private function processDownloadQueue()
    {
        while (false !== ($serializedEntity = $this->getQueue()->dequeue(Queue::QUEUE_NAME_DOWNLOAD_QUEUE))) {

            $entity = SubmissionEntity::fromArray($serializedEntity, $this->getLogger());

            do_action(ExportedAPI::ACTION_SMARTLING_DOWNLOAD_TRANSLATION, $entity);
        }
    }
}