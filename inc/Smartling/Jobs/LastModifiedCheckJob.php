<?php

namespace Smartling\Jobs;

use Smartling\ApiWrapperInterface;
use Smartling\Exception\SmartlingNetworkException;
use Smartling\Queue\Queue;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;

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
     * Checks changes in last-modified field and triggers statusCheck by fileUri if lastModified has changed.
     */
    private function lastModifiedCheck()
    {
        while (false !== ($serializedPair = $this->getQueue()->dequeue(Queue::QUEUE_NAME_LAST_MODIFIED_CHECK_QUEUE))) {
            foreach ($serializedPair as $fileUri => $serializedSubmissions) {
                $submissionList = $this->getSubmissionManager()->unserializeSubmissions($serializedSubmissions);

                try {
                    $lastModified = $this->getApiWrapper()->lastModified(reset($submissionList));
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

                $submissions = $this->prepareSubmissionList($submissionList);
                $needStatusCheck = false;

                foreach ($lastModified as $smartlingLocaleId => $lastModifiedDateTime) {
                    if (array_key_exists($smartlingLocaleId, $submissions)) {

                        /**
                         * @var \DateTime $lastModifiedDateTime
                         */

                        /**
                         * @var SubmissionEntity $submission
                         */
                        $submission = $submissions[$smartlingLocaleId];
                        $submissionLastModifiedTimestamp = $submission->getLastModified()->getTimestamp();
                        $actualLastModifiedTimestamp = $lastModifiedDateTime->getTimestamp();
                        if ($actualLastModifiedTimestamp !== $submissionLastModifiedTimestamp) {
                            $submission->setLastModified($lastModifiedDateTime);
                            $needStatusCheck = true;
                        } else {
                            /**
                             * pass next only changed that definitely need check status
                             */
                            unset($submissions[$smartlingLocaleId]);
                        }
                    }
                }

                if (true === $needStatusCheck && 0 < count($submissions)) {
                    $submissions = $this->getSubmissionManager()->storeSubmissions($submissions);
                    try {
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
        if (100 === $entity->getCompletionPercentage()) {

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

            $this->getQueue()->enqueue($entity->toArray(false), Queue::QUEUE_NAME_DOWNLOAD_QUEUE);
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
