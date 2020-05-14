<?php

namespace Smartling\Jobs;

use Smartling\ApiWrapperInterface;
use Smartling\Base\ExportedAPI;
use Smartling\Exceptions\SmartlingApiException;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\QueryBuilder\TransactionManager;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

/**
 * Class UploadJob
 * @package Smartling\Jobs
 */
class UploadJob extends JobAbstract
{

    const JOB_HOOK_NAME = 'smartling-upload-task';

    /**
     * @var ApiWrapperInterface
     */
    private $api;

    /**
     * @var SettingsManager
     */
    private $settingsManager;

    /**
     * @return \Smartling\ApiWrapperInterface
     */
    public function getApi()
    {
        return $this->api;
    }

    /**
     * @param \Smartling\ApiWrapperInterface $api
     */
    public function setApi($api)
    {
        $this->api = $api;
    }

    /**
     * @return \Smartling\Settings\SettingsManager
     */
    public function getSettingsManager()
    {
        return $this->settingsManager;
    }

    /**
     * @param \Smartling\Settings\SettingsManager $settingsManager
     */
    public function setSettingsManager($settingsManager)
    {
        $this->settingsManager = $settingsManager;
    }

    /**
     * @param SubmissionManager $submissionManager
     * @param int $workerTTL
     * @param ApiWrapperInterface $api
     * @param SettingsManager $settingsManager
     * @param TransactionManager $transactionManager
     */
    public function __construct(
        SubmissionManager $submissionManager,
        $workerTTL,
        ApiWrapperInterface $api,
        SettingsManager $settingsManager,
        TransactionManager $transactionManager
    ) {
        parent::__construct($submissionManager, $transactionManager, $workerTTL);

        $this->setApi($api);
        $this->setSettingsManager($settingsManager);
    }

    /**
     * @return string
     */
    public function getJobHookName()
    {
        return self::JOB_HOOK_NAME;
    }

    /**
     * @inheritdoc
     */
    public function run()
    {
        $this->getLogger()->info('Started UploadJob.');

        $this->processUploadQueue();

        $this->setBatchUidForItemsInUploadQueue();

        $this->getLogger()->info('Finished UploadJob.');
    }

    private function processUploadQueue() {
        do {
            $entities = $this->getSubmissionManager()->findSubmissionsForUploadJob();

            if (0 === count($entities)) {
                break;
            }

            // refreshing the lock flag value
            $this->placeLockFlag();

            $entity = ArrayHelper::first($entities);
            /**
             * @var SubmissionEntity $entity
             */
            $this->getLogger()->info(
                vsprintf(
                    'Cron Job triggers content upload for submission id="%s" with status="%s" for entity="%s", blog="%s", id="%s", targetBlog="%s", locale="%s", batchUid="%s".',
                    [
                        $entity->getId(),
                        $entity->getStatus(),
                        $entity->getContentType(),
                        $entity->getSourceBlogId(),
                        $entity->getSourceId(),
                        $entity->getTargetBlogId(),
                        $entity->getTargetLocale(),
                        $entity->getBatchUid(),
                    ]
                )
            );

            do_action(ExportedAPI::ACTION_SMARTLING_SEND_FILE_FOR_TRANSLATION, $entity);
            $this->placeLockFlag(true);
        } while (0 < count($entities));
    }

    private function setBatchUidForItemsInUploadQueue() {
        // Daily bucket job.
        foreach ($this->getSettingsManager()->getActiveProfiles() as $activeProfile) {
            /**
             * @var ConfigurationProfileEntity $activeProfile.
             */
            $originalBlogId = $activeProfile->getOriginalBlogId()->getBlogId();
            $entities = $this->getSubmissionManager()->find(
                [
                    SubmissionEntity::FIELD_STATUS         => [SubmissionEntity::SUBMISSION_STATUS_NEW],
                    SubmissionEntity::FIELD_IS_LOCKED      => 0,
                    SubmissionEntity::FIELD_IS_CLONED      => 0,
                    SubmissionEntity::FIELD_BATCH_UID      => '',
                    SubmissionEntity::FIELD_SOURCE_BLOG_ID => $originalBlogId,
                ]
            );

            if (!empty($entities)) {
                $this->getLogger()->info('Started dealing with daily bucket job.');

                try {
                    $batchUid = $this->getApi()->retrieveBatchForBucketJob($activeProfile, (bool) $activeProfile->getAutoAuthorize());

                    foreach ($entities as $entity) {
                        if (empty($batchUid)) {
                            $this->getLogger()->warning(
                                vsprintf(
                                    'Cron Job failed to mark content for upload into daily bucket job for submission id="%s" with status="%s" for entity="%s", blog="%s", id="%s", targetBlog="%s", locale="%s", batchUid="%s".',
                                    [
                                        $entity->getId(),
                                        $entity->getStatus(),
                                        $entity->getContentType(),
                                        $entity->getSourceBlogId(),
                                        $entity->getSourceId(),
                                        $entity->getTargetBlogId(),
                                        $entity->getTargetLocale(),
                                        $entity->getBatchUid(),
                                    ]
                                )
                            );

                            continue;
                        }

                        $entity->setBatchUid($batchUid);

                        $this->getLogger()->info(
                            vsprintf(
                                'Cron Job marks content for upload into daily bucket job for submission id="%s" with status="%s" for entity="%s", blog="%s", id="%s", targetBlog="%s", locale="%s", batchUid="%s".',
                                [
                                    $entity->getId(),
                                    $entity->getStatus(),
                                    $entity->getContentType(),
                                    $entity->getSourceBlogId(),
                                    $entity->getSourceId(),
                                    $entity->getTargetBlogId(),
                                    $entity->getTargetLocale(),
                                    $entity->getBatchUid(),
                                ]
                            )
                        );
                    }

                    $this->getSubmissionManager()->storeSubmissions($entities);
                } catch (SmartlingApiException $e) {
                    $this->getLogger()->error($e->formatErrors());
                }

                $this->getLogger()->info('Finished dealing with daily bucket job.');
            }
        }
    }


}
