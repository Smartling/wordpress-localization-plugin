<?php

namespace Smartling\Jobs;

use Smartling\ApiWrapperInterface;
use Smartling\Helpers\Cache;
use Smartling\Helpers\FileUriHelper;
use Smartling\Queue\Queue;
use Smartling\Queue\QueueInterface;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

class SubmissionCollectorJob extends JobAbstract
{
    public const JOB_HOOK_NAME = 'smartling-submission-collector-task';

    public function __construct(
        ApiWrapperInterface $api,
        Cache $cache,
        private FileUriHelper $fileUriHelper,
        SettingsManager $settingsManager,
        SubmissionManager $submissionManager,
        int $throttleIntervalSeconds,
        string $jobRunInterval,
        int $workerTTL,
        private QueueInterface $queue,
    ) {
        parent::__construct($api, $cache, $settingsManager, $submissionManager, $throttleIntervalSeconds, $jobRunInterval, $workerTTL);
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
                $submission->setFileUri($this->fileUriHelper->generateFileUri($submission));
            }
            $this->submissionManager->storeSubmissions($submissions);
        } while (0 < count($submissions));
    }

    public function run(string $source): void
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
