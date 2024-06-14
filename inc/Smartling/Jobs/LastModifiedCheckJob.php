<?php

namespace Smartling\Jobs;

use Smartling\ApiWrapperInterface;
use Smartling\Exception\SmartlingNetworkException;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\Cache;
use Smartling\Helpers\TestRunHelper;
use Smartling\Queue\QueueInterface;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

class LastModifiedCheckJob extends JobAbstract
{
    public const JOB_HOOK_NAME = 'smartling-last-modified-check-task';

    public function __construct(
        ApiWrapperInterface $api,
        Cache $cache,
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

    public function run(): void
    {
        $this->getLogger()->info('Started Last-Modified Check Job.');

        $this->lastModifiedCheck(QueueInterface::QUEUE_NAME_LAST_MODIFIED_CHECK_QUEUE, false);
        $this->lastModifiedCheck(QueueInterface::QUEUE_NAME_LAST_MODIFIED_CHECK_AND_FAIL_QUEUE, true);

        $this->getLogger()->info('Finished Last-Modified Check Job.');
    }

    /**
     * @param SubmissionEntity[] $submissions
     * @return SubmissionEntity[]
     */
    protected function filterSubmissions(array $lastModifiedResponse, array $submissions, bool $failMissing): array
    {
        $filteredSubmissions = [];
        foreach ($lastModifiedResponse as $smartlingLocaleId => $lastModifiedDateTime) {
            if (array_key_exists($smartlingLocaleId, $submissions)) {
                /**
                 * @var \DateTime $lastModifiedDateTime
                 */
                $submission = $submissions[$smartlingLocaleId];
                $submissionLastModifiedTimestamp = $submission->getLastModified()->getTimestamp();
                $actualLastModifiedTimestamp = $lastModifiedDateTime->getTimestamp();
                if ($actualLastModifiedTimestamp !== $submissionLastModifiedTimestamp) {
                    $filteredSubmissions[$smartlingLocaleId] = $submission;
                    $submission->setLastModified($lastModifiedDateTime);
                } elseif ($submission->getStatus() === SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS
                          && $submission->getCompletionPercentage() === 100
                ) {
                    // We need this statement for the case, when something went wrong, and
                    // submission appeared in a situation with Progress == 100% and Status == "In progress"
                    // We need to download such submission at some point. But it won't happen
                    // without this fix
                    $filteredSubmissions[$smartlingLocaleId] = $submission;
                }
                unset($submissions[$smartlingLocaleId]);
            }
        }
        if ($failMissing) {
            foreach ($submissions as $submission) {
                $submission->setLastModified(new \DateTime());
                $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_FAILED);
                $message = 'File submitted for locales ' .
                    implode(', ', array_keys($lastModifiedResponse)) . '. This submission is for locale ' .
                    $this->getSmartlingLocaleIdBySubmission($submission);
                $submission->setLastError($message);
                $this->getLogger()->notice($message);
            }
            $this->submissionManager->storeSubmissions(array_values($submissions));
        }

        return $filteredSubmissions;
    }

    /**
     * @param SubmissionEntity[] $submissions
     * @return SubmissionEntity[]
     */
    protected function processFileUriSet(array $submissions, bool $failMissing): array
    {
        if (ArrayHelper::notEmpty($submissions)) {
            $submission = ArrayHelper::first($submissions);
            try {
                $lastModified = $this->api->lastModified($submission);
            } catch (SmartlingNetworkException $e) {
                if ($this->api->isUnrecoverable($e)) {
                    $this->submissionManager->setErrorMessage($submission, $e->getMessage());
                }
                throw $e;
            }
            $submissions = $this->prepareSubmissionList($submissions);
            $submissions = $this->filterSubmissions($lastModified, $submissions, $failMissing);
            $this->placeLockFlag(true);
        }

        return $submissions;
    }

    /**
     * @param array $serializedPair
     * @return bool
     */
    private function validateSerializedPair(array $serializedPair): bool
    {
        $result = false;
        if (1 === count($serializedPair)) {
            $key = ArrayHelper::first(array_keys($serializedPair));
            if (is_string($key) && 0 < strlen($key) && is_array($serializedPair[$key]) && 0 < count($serializedPair[$key])) {
                foreach ($serializedPair[$key] as $item) {
                    if (!is_numeric($item)) {
                        return $result;
                    }
                }
                $result = true;
            }
        }

        return $result;
    }

    /**
     * Checks changes in last-modified field and triggers statusCheck by fileUri if lastModified has changed.
     */
    private function lastModifiedCheck(string $queueName, bool $failMissing): void
    {
        while (true) {
            $serializedPair = $this->queue->dequeue($queueName);
            if (!is_array($serializedPair)) {
                break;
            }
            if (false === $this->validateSerializedPair($serializedPair)) {
                $this->getLogger()->warning(vsprintf('Got unexpected data from queue : \'%s\'. Skipping', [
                    var_export($serializedPair, true),
                ]));
                continue;
            }
            foreach ($serializedPair as $serializedSubmissions) {
                $submissionList = $this->processTestRun($this->submissionManager->findByIds($serializedSubmissions));

                try {
                    $submissions = $this->processFileUriSet($submissionList, $failMissing);
                } catch (SmartlingNetworkException $e) {
                    $this->getLogger()
                        ->error(
                            vsprintf(
                                'An exception has occurred while executing ApiWrapper::lastModified. Message: %s.',
                                [$e->getMessage()]
                            )
                        );
                    continue;
                }

                $submissions = $this->submissionManager->storeSubmissions($submissions);

                if (0 < count($submissions)) {
                    try {
                        $this->processDownloadOnChange($submissions);
                        $this->statusCheck($submissions);
                    } catch (SmartlingNetworkException $e) {
                        $this->getLogger()
                            ->error(
                                vsprintf(
                                    'An exception has occurred while executing ApiWrapper::getStatusForAllLocales. Message: %s.',
                                    [$e->getMessage()]
                                )
                            );
                        continue;
                    }
                }
            }
        }
    }

    /**
     * @param SubmissionEntity[] $submissions
     */
    protected function processDownloadOnChange(array $submissions): void
    {
        foreach ($submissions as $submission) {
            $profile = $this->settingsManager->getSingleSettingsProfile($submission->getSourceBlogId());

            if (($profile instanceof ConfigurationProfileEntity) && 1 === $profile->getDownloadOnChange()) {
                $this->getLogger()
                    ->debug(vsprintf('Adding submission %s to Download queue as it was changed.', [$submission->getId()]));
                $this->queue->enqueue([$submission->getId()], QueueInterface::QUEUE_NAME_DOWNLOAD_QUEUE);
            }
        }
    }

    /**
     * @param SubmissionEntity[] $submissions
     *
     * @return SubmissionEntity[]
     */
    private function processTestRun(array $submissions): array
    {
        $result = [];
        foreach ($submissions as $submission) {
            if ($submission->getStatus() === SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS && TestRunHelper::isTestRunBlog($submission->getTargetBlogId())) {
                $this->queue->enqueue([$submission->getId()], QueueInterface::QUEUE_NAME_DOWNLOAD_QUEUE);
            } else {
                $result[] = $submission;
            }
        }

        return $result;
    }

    /**
     * @param SubmissionEntity[] $submissions
     * @throws SmartlingNetworkException
     */
    public function statusCheck(array $submissions): void
    {
        $this->getLogger()->debug(vsprintf('Processing status check for %d submissions.', [count($submissions)]));

        $submissions = $this->prepareSubmissionList($submissions);

        $statusCheckResult = $this->api->getStatusForAllLocales($submissions);

        $submissions = $this->submissionManager->storeSubmissions($statusCheckResult);

        foreach ($submissions as $submission) {
            $this->checkEntityForDownload($submission);
        }

        $this->getLogger()->debug('Processing status check finished.');
    }

    /**
     * @param SubmissionEntity $entity
     */
    public function checkEntityForDownload(SubmissionEntity $entity): void
    {
        if (100 === $entity->getCompletionPercentage() && 1 !== $entity->getIsCloned()) {

            $template = 'Cron Job enqueues content to download queue for submission id = \'%s\' with status = \'%s\' for entity = \'%s\', blog = \'%s\', id = \'%s\', targetBlog = \'%s\', locale = \'%s\'.';

            $message = vsprintf($template, [
                $entity->getId(),
                $entity->getStatus(),
                $entity->getContentType(),
                $entity->getSourceBlogId(),
                $entity->getSourceId(),
                $entity->getTargetBlogId(),
                $entity->getTargetLocale(),
            ]);

            $this->getLogger()->info($message);

            $this->queue->enqueue([$entity->getId()], QueueInterface::QUEUE_NAME_DOWNLOAD_QUEUE);
        }
    }

    /**
     * @param SubmissionEntity $submission
     * @return string
     */
    public function getSmartlingLocaleIdBySubmission(SubmissionEntity $submission): string
    {
        return $this->settingsManager
            ->getSmartlingLocaleIdBySettingsProfile(
                $this->settingsManager->getSingleSettingsProfile($submission->getSourceBlogId()),
                $submission->getTargetBlogId()
            );
    }

    /**
     * @param SubmissionEntity[] $submissionList
     * @return array
     */
    public function prepareSubmissionList(array $submissionList): array
    {
        $output = [];

        foreach ($submissionList as $submissionEntity) {
            $smartlingLocaleId = $this->getSmartlingLocaleIdBySubmission($submissionEntity);
            $output[$smartlingLocaleId] = $submissionEntity;
        }

        return $output;
    }
}
