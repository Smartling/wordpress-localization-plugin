<?php

namespace Smartling\Jobs;

use Smartling\Queue\Queue;
use Smartling\Submissions\SubmissionEntity;

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
     * @return executes job
     */
    public function run()
    {
        $this->getLogger()->info('Started Submission Collector Job.');

        $preparedArray = $this->gatherSubmissions();

        if (0 < count($preparedArray)) {
            foreach ($preparedArray as $fileUri => $submissionList) {

                $submissionIds = [];

                foreach ($submissionList as $submission) {
                    $submissionIds = $submission->getId();
                }
                
                $this->getQueue()
                    ->enqueue([$fileUri => $submissionIds], Queue::QUEUE_NAME_LAST_MODIFIED_CHECK_QUEUE);
            }
        }

        $this->getLogger()->info('Finished Submission Collector Job.');
    }

    /**
     * Gathers submissions in statuses 'In Progress', 'Completed' and groups them by fileUri
     * @return array
     */
    private function gatherSubmissions()
    {
        $entities = $this->getSubmissionManager()->find(
            [
                'status' => [
                    SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS,
                    SubmissionEntity::SUBMISSION_STATUS_COMPLETED,
                ],
            ]);

        return $this->groupSubmissionsByFileUri($entities);
    }

    /**
     * Groups a set of SubmissionEntity by fileUri for batch requests.
     *
     * @param SubmissionEntity[] $submissions
     *
     * @return array
     */
    private function groupSubmissionsByFileUri(array $submissions)
    {
        $grouped = [];

        foreach ($submissions as $submission) {
            /**
             * @var SubmissionEntity $submission
             */
            $grouped[$submission->getFileUri()][] = $submission;
        }

        return $grouped;
    }
}