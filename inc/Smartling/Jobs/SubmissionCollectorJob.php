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
     * @param int $iterationLimit
     */
    private function fixEmptyFileUriSubmissions($iterationLimit = 100)
    {
        $params = [
            SubmissionEntity::FIELD_FILE_URI => '',
        ];

        do {
            $this->getLogger()
                ->debug(vsprintf('Trying to get %d submissions with empty fileUri from database.', [$iterationLimit]));
            $submissions = $this->getSubmissionManager()->find($params, $iterationLimit);
            $this->getLogger()->debug(vsprintf('Found %d submissions with empty fileUri.', [count($submissions)]));
            if (0 === count($submissions)) {
                break;
            }
            foreach ($submissions as $submission) {
                $submission->getFileUri();
            }
            $this->getSubmissionManager()->storeSubmissions($submissions);
        } while (0 < count($submissions));
    }

    public function run()
    {
        $this->getLogger()->info('Started Submission Collector Job.');
        $this->fixEmptyFileUriSubmissions();
        $preparedList = $this->getSubmissionManager()->getGroupedIdsByFileUri();
        if (0 < count($preparedList)) {
            foreach ($preparedList as $_result) {
                $fileUri = &$_result['fileUri'];
                $idsList = explode(',', $_result['ids']);
                array_walk($idsList, function (& $id) {
                    $id = (int)$id;
                });
                $this->getQueue()->enqueue([$fileUri => $idsList], Queue::QUEUE_NAME_LAST_MODIFIED_CHECK_QUEUE);
            }
        }
        $this->getLogger()->info('Finished Submission Collector Job.');
    }
}
