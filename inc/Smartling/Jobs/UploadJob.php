<?php

namespace Smartling\Jobs;

use Smartling\Base\ExportedAPI;
use Smartling\Exceptions\SmartlingApiException;
use Smartling\Helpers\ArrayHelper;
use Smartling\Submissions\SubmissionEntity;

class UploadJob extends JobAbstract
{
    public const JOB_HOOK_NAME = 'smartling-upload-task';

    public function getJobHookName(): string
    {
        return self::JOB_HOOK_NAME;
    }

    public function run(): void
    {
        $this->getLogger()->info('Started UploadJob.');

        $this->processUploadQueue();

        $this->processCloning();

        $this->processDailyBucketJob();

        $this->getLogger()->info('Finished UploadJob.');
    }

    private function processUploadQueue(): void
    {
        while (($entity = $this->submissionManager->findSubmissionForUploadJob()) !== null) {
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

            try {
                do_action(ExportedAPI::ACTION_SMARTLING_SEND_FILE_FOR_TRANSLATION, $entity);
            } catch (\Exception $e) {
                $this->getLogger()->notice(sprintf('Failing submissionId=%s: %s', $entity->getId(), $e->getMessage()));
                $this->submissionManager->setErrorMessage($entity, $e->getMessage());
            }
            $this->placeLockFlag(true);
        }
    }

    private function processCloning(): void
    {
        while (($submission = $this->submissionManager->findSubmissionForCloning()) !== null) {
            do_action(ExportedAPI::ACTION_SMARTLING_PREPARE_SUBMISSION_UPLOAD, $submission);
            do_action(ExportedAPI::ACTION_SMARTLING_CLONE_CONTENT, $submission);
        }
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
            $entities = $this->submissionManager->find(
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
                    $jobInfo = $this->api->retrieveJobInfoForDailyBucketJob($activeProfile, $activeProfile->getAutoAuthorize());

                    foreach ($entities as $entity) {
                        if ($jobInfo->getBatchUid() === '') {
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

                        $entity->setBatchUid($jobInfo->getBatchUid());
                        $entity->setJobInfo($jobInfo->getJobInformationEntity());

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

                    $this->submissionManager->storeSubmissions($entities);
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
