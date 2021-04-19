<?php

namespace Smartling\Jobs;

use Smartling\ApiWrapperInterface;
use Smartling\Base\ExportedAPI;
use Smartling\Exceptions\SmartlingApiException;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\QueryBuilder\TransactionManager;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

class UploadJob extends JobAbstract
{
    public const JOB_HOOK_NAME = 'smartling-upload-task';

    private ApiWrapperInterface $api;
    private SettingsManager $settingsManager;

    public function __construct(
        SubmissionManager $submissionManager,
        int $workerTTL,
        ApiWrapperInterface $api,
        SettingsManager $settingsManager,
        TransactionManager $transactionManager
    ) {
        parent::__construct($submissionManager, $transactionManager, $workerTTL);

        $this->api = $api;
        $this->settingsManager = $settingsManager;
    }

    public function getJobHookName(): string
    {
        return self::JOB_HOOK_NAME;
    }

    public function run(): void
    {
        $this->getLogger()->info('Started UploadJob.');

        $this->processUploadQueue();

        $this->processDailyBucketJob();

        $this->getLogger()->info('Finished UploadJob.');
    }

    private function processUploadQueue(): void
    {
        do {
            $entities = $this->getSubmissionManager()->findSubmissionsForUploadJob();

            if (0 === count($entities)) {
                break;
            }

            // refreshing the lock flag value
            $this->placeLockFlag();

            $entity = ArrayHelper::first($entities);
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

    private function processDailyBucketJob(): void
    {
        foreach ($this->settingsManager->getActiveProfiles() as $activeProfile) {
            if ($activeProfile->getUploadOnUpdate() === 0) {
                $this->getLogger()->debug(sprintf(
                    "Skipping profile projectId: %s for daily bucket job processing",
                    $activeProfile->getProjectId()
                ));
                continue;
            }
            $originalBlogId = $activeProfile->getOriginalBlogId()->getBlogId();
            $entities = $this->getSubmissionManager()->find(
                [
                    SubmissionEntity::FIELD_STATUS => [SubmissionEntity::SUBMISSION_STATUS_NEW],
                    SubmissionEntity::FIELD_IS_LOCKED => 0,
                    SubmissionEntity::FIELD_IS_CLONED => 0,
                    SubmissionEntity::FIELD_BATCH_UID => '',
                    SubmissionEntity::FIELD_SOURCE_BLOG_ID => $originalBlogId,
                ]
            );

            if (!empty($entities)) {
                $this->getLogger()->info('Started dealing with daily bucket job.');

                try {
                    $batchUid = $this->api->retrieveBatchForBucketJob($activeProfile, (bool) $activeProfile->getAutoAuthorize());

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
                } catch (\Exception $e) {
                    $this->getLogger()->error($e->getMessage());
                }

                $this->getLogger()->info('Finished dealing with daily bucket job.');
            }
        }
    }
}
