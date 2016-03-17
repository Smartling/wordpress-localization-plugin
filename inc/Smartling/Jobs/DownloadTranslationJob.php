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
        return 'smartling-download-task';
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
        while (false !== ($serializedEntity = $this->getQueue()->dequeue('download-queue'))) {

            $entity = SubmissionEntity::fromArray($serializedEntity, $this->getLogger());

            do_action(ExportedAPI::ACTION_SMARTLING_DOWNLOAD_TRANSLATION, $entity);
        }
    }
}