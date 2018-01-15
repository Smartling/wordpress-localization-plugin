<?php

namespace Smartling\Jobs;

use Smartling\ApiWrapperInterface;
use Smartling\Exception\SmartlingNetworkException;
use Smartling\Helpers\ArrayHelper;
use Smartling\MonologWrapper\MonologWrapper;
use Smartling\Queue\Queue;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

/**
 * Class LastModifiedCheckJob
 * @package Smartling\Jobs
 */
class LastModifiedCheckJob extends JobAbstract
{
    /**
     * @var ApiWrapperInterface
     */
    private $apiWrapper;

    /**
     * @var SettingsManager
     */
    private $settingsManager;

    /**
     * @var Queue
     */
    private $queue;

    /**
     * @return ApiWrapperInterface
     */
    public function getApiWrapper()
    {
        return $this->apiWrapper;
    }

    /**
     * @param ApiWrapperInterface $apiWrapper
     */
    public function setApiWrapper($apiWrapper)
    {
        $this->apiWrapper = $apiWrapper;
    }

    /**
     * @return SettingsManager
     */
    public function getSettingsManager()
    {
        return $this->settingsManager;
    }

    /**
     * @param SettingsManager $settingsManager
     */
    public function setSettingsManager($settingsManager)
    {
        $this->settingsManager = $settingsManager;
    }

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

    const JOB_HOOK_NAME = 'smartling-last-modified-check-task';

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
        $this->getLogger()->info('Started Last-Modified Check Job.');

        $this->lastModifiedCheck();

        $this->getLogger()->info('Finished Last-Modified Check Job.');
    }

    /**
     * Gets serialized pair from queue
     * @return array|false
     */
    protected function getSerializedPair()
    {
        return $this->getQueue()->dequeue(Queue::QUEUE_NAME_LAST_MODIFIED_CHECK_QUEUE);
    }

    /**
     * @param array              $lastModifiedResponse
     * @param SubmissionEntity[] $submissions
     *
     * @return SubmissionEntity[]
     */
    protected function filterSubmissions(array $lastModifiedResponse, array $submissions)
    {
        $filteredSubmissions = [];
        foreach ($lastModifiedResponse as $smartlingLocaleId => $lastModifiedDateTime) {
            if (array_key_exists($smartlingLocaleId, $submissions)) {
                /**
                 * @var \DateTime        $lastModifiedDateTime
                 * @var SubmissionEntity $submission
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
                    // We need to download such submission at some point. But it won'd happen
                    // without this fix
                    $filteredSubmissions[$smartlingLocaleId] = $submission;
                }
            }
        }

        return $filteredSubmissions;
    }

    /**
     * @param \Smartling\Submissions\SubmissionEntity[] $submissions
     *
     * @return \Smartling\Submissions\SubmissionEntity[]
     */
    protected function processFileUriSet(array $submissions)
    {
        if (ArrayHelper::notEmpty($submissions)) {
            $submission = ArrayHelper::first($submissions);
            $lastModified = $this->getApiWrapper()->lastModified($submission);
            $submissions = $this->prepareSubmissionList($submissions);
            $submissions = $this->filterSubmissions($lastModified, $submissions);
        }

        return $submissions;
    }

    /**
     * @param \Smartling\Submissions\SubmissionEntity[] $submissions
     *
     * @return \Smartling\Submissions\SubmissionEntity[]
     */
    protected function storeSubmissions(array $submissions)
    {
        if (0 < count($submissions)) {
            $submissions = $this->getSubmissionManager()->storeSubmissions($submissions);
        }

        return $submissions;
    }

    /**
     * @param array $serializedPair
     *
     * @return bool
     */
    private function validateSerializedPair(array $serializedPair)
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
    private function lastModifiedCheck()
    {
        while (false !== ($serializedPair = $this->getSerializedPair())) {
            if (false === $this->validateSerializedPair($serializedPair)) {
                $this->getLogger()->warning(vsprintf('Got unexpected data from queue : \'%s\'. Skipping', [
                    var_export($serializedPair, true),
                ]));
                continue;
            }
            /**
             * @var array $serializedPair
             */
            foreach ($serializedPair as $fileUri => $serializedSubmissions) {
                $submissionList = $this->getSubmissionManager()->findByIds($serializedSubmissions);

                try {
                    $submissions = $this->processFileUriSet($submissionList);
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

                $submissions = $this->storeSubmissions($submissions);

                if (0 < count($submissions)) {

                    try {
                        $this->processDownloadOnChange($submissions);
                        $this->statusCheck($submissions);
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
                }
            }
        }
    }

    /**
     * @param SubmissionEntity[] $submissions
     */
    protected function processDownloadOnChange(array $submissions)
    {
        foreach ($submissions as $submission) {
            $profile = $this->getSettingsManager()->getSingleSettingsProfile($submission->getSourceBlogId());

            if (($profile instanceof ConfigurationProfileEntity) && 1 === $profile->getDownloadOnChange()) {
                $this->getLogger()
                    ->debug(vsprintf('Adding submission %s to Download queue as it was changed.', [$submission->getId()]));
                $this->getQueue()->enqueue([$submission->getId()], Queue::QUEUE_NAME_DOWNLOAD_QUEUE);
            }
        }
    }

    /**
     * @param SubmissionEntity[] $submissions
     *
     * @throws SmartlingNetworkException
     */
    public function statusCheck(array $submissions)
    {
        $this->getLogger()->debug(vsprintf('Processing status check for %d submissions.', [count($submissions)]));

        $submissions = $this->prepareSubmissionList($submissions);

        $statusCheckResult = $this->getApiWrapper()->getStatusForAllLocales($submissions);

        $submissions = $this->getSubmissionManager()->storeSubmissions($statusCheckResult);

        foreach ($submissions as $submission) {
            $this->checkEntityForDownload($submission);
        }

        $this->getLogger()->debug('Processing status check finished.');
    }

    /**
     * @param SubmissionEntity $entity
     */
    public function checkEntityForDownload(SubmissionEntity $entity)
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

            $this->getQueue()->enqueue([$entity->getId()], Queue::QUEUE_NAME_DOWNLOAD_QUEUE);
        }
    }

    /**
     * @param SubmissionEntity $submission
     *
     * @return string
     */
    private function getSmartlingLocaleIdBySubmission(SubmissionEntity $submission)
    {
        $settingsManager = $this->getSettingsManager();

        $locale = $settingsManager
            ->getSmartlingLocaleIdBySettingsProfile(
                $settingsManager->getSingleSettingsProfile($submission->getSourceBlogId()),
                $submission->getTargetBlogId()
            );

        return $locale;
    }

    /**
     * @param SubmissionEntity[] $submissionList
     *
     * @return array
     */
    public function prepareSubmissionList(array $submissionList)
    {
        $output = [];

        foreach ($submissionList as $submissionEntity) {
            $smartlingLocaleId = $this->getSmartlingLocaleIdBySubmission($submissionEntity);
            $output[$smartlingLocaleId] = $submissionEntity;
        }

        return $output;
    }
}
