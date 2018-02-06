<?php

namespace Smartling\Jobs;

use Smartling\ApiWrapperInterface;
use Smartling\Base\ExportedAPI;
use Smartling\Exceptions\SmartlingApiException;
use Smartling\Helpers\ArrayHelper;
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

    public function __construct(SubmissionManager $submissionManager, $workerTTL, ApiWrapperInterface $api, SettingsManager $settingsManager) {
        parent::__construct($submissionManager, $workerTTL);

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
            $entities = $this->getSubmissionManager()->findBatchUidNotEmpty(
                [
                    'status'    => [SubmissionEntity::SUBMISSION_STATUS_NEW],
                    'is_locked' => [0],
                ], 1);

            if (0 === count($entities)) {
                break;
            }

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
                    'status'    => [SubmissionEntity::SUBMISSION_STATUS_NEW],
                    'is_locked' => [0],
                    'batch_uid' => '',
                    'source_blog_id' => $originalBlogId,
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

                $this->getLogger()->info('Started uploading to daily job.');

                $this->processUploadQueue();

                $this->getLogger()->info('Finished uploading to daily job.');
            }
        }
    }


}
