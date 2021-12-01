<?php

namespace Smartling\Jobs;

use Smartling\ApiWrapperInterface;
use Smartling\Queue\Queue;
use Smartling\Queue\QueueInterface;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

class SubmissionCollectorJob extends JobAbstract
{
    public const JOB_HOOK_NAME = 'smartling-submission-collector-task';

    private QueueInterface $queue;

    public function __construct(
        ApiWrapperInterface $api,
        SettingsManager $settingsManager,
        SubmissionManager $submissionManager,
        string $jobRunInterval,
        int $workerTTL,
        QueueInterface $queue
    ) {
        parent::__construct($api, $settingsManager, $submissionManager, $jobRunInterval, $workerTTL);
        $this->queue = $queue;
    }

    public function getJobHookName(): string
    {
        return self::JOB_HOOK_NAME;
    }

    private function fixEmptyFileUriSubmissions(int $iterationLimit = 100): void
    {
        $params = [
            SubmissionEntity::FIELD_FILE_URI => '',
        ];

        do {
            $this->getLogger()
                ->debug(vsprintf('Trying to get %d submissions with empty fileUri from database.', [$iterationLimit]));
            $submissions = $this->submissionManager->find($params, $iterationLimit);
            $this->getLogger()->debug(vsprintf('Found %d submissions with empty fileUri.', [count($submissions)]));
            if (0 === count($submissions)) {
                break;
            }
            foreach ($submissions as $submission) {
                $submission->getFileUri();
            }
            $this->submissionManager->storeSubmissions($submissions);
        } while (0 < count($submissions));
    }

    public function run(): void
    {
        $this->getLogger()->info('Started Submission Collector Job.');
        $this->fixEmptyFileUriSubmissions();
        $preparedList = $this->submissionManager->getGroupedIdsByFileUri();
        if (0 < count($preparedList)) {
            foreach ($preparedList as $_result) {
                $fileUri = &$_result['fileUri'];
                $idsList = explode(',', $_result['ids']);
                array_walk($idsList, function (& $id) {
                    $id = (int)$id;
                });
                $this->queue->enqueue([$fileUri => $idsList], Queue::QUEUE_NAME_LAST_MODIFIED_CHECK_QUEUE);
            }
        }
        $this->getLogger()->info('Finished Submission Collector Job.');
    }
}
