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

    /**
     * @return string
     */
    public function getJobHookName()
    {
        return 'smartling-submission-collector-task';
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
                $serializedSubmissions = $this->getSubmissionManager()->serializeSubmissions($submissionList);

                $this->getQueue()->enqueue([$fileUri => $serializedSubmissions], 'last-modified-check-queue');
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