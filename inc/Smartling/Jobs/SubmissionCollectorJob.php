<?php

namespace Smartling\Jobs;

use Smartling\Queue\Queue;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

/**
 * Class SubmissionCollectorJob
 * @package Smartling\Jobs
 */
class SubmissionCollectorJob extends JobAbstract
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

    const JOB_HOOK_NAME = 'smartling-submission-collector-task';

    /**
     * @return string
     */
    public function getJobHookName()
    {
        return self::JOB_HOOK_NAME;
    }

    /**
     * @return array
     */
    private function getFileList()
    {
        $this->getLogger()->info(vsprintf('Getting list of files...', []));
        $entities = $this->getSubmissionManager()->find(
            [
                'status' => [
                    SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS,
                    SubmissionEntity::SUBMISSION_STATUS_COMPLETED,
                ],
            ],
            0,
            ['file_uri']
        );

        $fileList = [];
        if (0 < count($entities)) {
            foreach ($entities as $entity) {
                $fileList[] = $entity->getFileUri();
            }
        }

        return $fileList;
    }

    /**
     * @param $fileUri
     *
     * @return int[]
     */
    private function getSubmissionIdsByFileUri($fileUri)
    {
        $this->getLogger()->info(vsprintf('Getting list of files...', []));
        $entities = $this->getSubmissionManager()->find(
            [
                'status'   => [
                    SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS,
                    SubmissionEntity::SUBMISSION_STATUS_COMPLETED,
                ],
                'file_uri' => $fileUri,
            ],
            0
        );
        $ids = [];
        $entities = $this->getSubmissionManager()->filterBrokenSubmissions($entities);
        foreach ($entities as $entity) {
            $ids[] = $entity->getId();
        }
        return $ids;
    }

    /**
     * @return executes job
     */
    public function run()
    {
        $this->getLogger()->info('Started Submission Collector Job.');
        $fileList = $this->getFileList();
        $this->getLogger()->info(vsprintf('Submission Collector Job. Got %s files', [count($fileList)]));
        if (0 < count($fileList)) {
            foreach ($fileList as $fileUri) {
                $submissionIds = $this->getSubmissionIdsByFileUri($fileUri);
                $this->getQueue()->enqueue([$fileUri => $submissionIds], Queue::QUEUE_NAME_LAST_MODIFIED_CHECK_QUEUE);
                $this->getLogger()->info(vsprintf('Submission Collector Job. Done file \'%s\'.', [$fileUri]));
            }

        }
        $this->getLogger()->info('Finished Submission Collector Job.');
    }
}
