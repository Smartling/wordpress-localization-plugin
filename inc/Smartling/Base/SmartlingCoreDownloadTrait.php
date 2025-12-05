<?php

namespace Smartling\Base;

use Smartling\Exception\SmartlingFileDownloadException;
use Smartling\Submissions\SubmissionEntity;
use Smartling\WP\Controller\LiveNotificationController;

trait SmartlingCoreDownloadTrait
{
    public function downloadTranslationBySubmission(SubmissionEntity $entity): void
    {
        $this->getLogger()->debug(vsprintf('Preparing to download submission id = \'%s\'.', [$entity->getId()]));
        if (1 === $entity->getIsLocked()) {
            $msg = vsprintf('Triggered download of locked entity. Target Blog: %s; Target Id: %s', [
                $entity->getTargetBlogId(),
                $entity->getTargetId(),
            ]);
            $this->getLogger()->warning($msg);

            return;
        }
        if (1 === $entity->getIsCloned()) {
            $msg = vsprintf('Triggered download of cloned entity. Target Blog: %s; Target Id: %s', [
                $entity->getTargetBlogId(),
                $entity->getTargetId(),
            ]);
            $this->getLogger()->warning($msg);

            return;
        }
        if (0 === $entity->getTargetId()) {
            $msg = vsprintf(
                'Cannot download \'%s\' (blog = \'%s\', id = \'%s\') fot blog = \'%s\' that doesn\'t have a translation placeholder yet. Please upload first.',
                [
                    $entity->getContentType(),
                    $entity->getSourceBlogId(),
                    $entity->getSourceId(),
                    $entity->getTargetBlogId(),
                ]
            );
            $entity->setLastError($msg);
            $entity->setStatus(SubmissionEntity::SUBMISSION_STATUS_FAILED);
            $this->getSubmissionManager()->storeEntity($entity);
            $this->getLogger()->warning($msg);
            return;
        }

        try {
            LiveNotificationController::pushNotification(
                $this
                    ->getSettingsManager()
                    ->getSingleSettingsProfile($entity->getSourceBlogId())
                    ->getProjectId(),
                LiveNotificationController::getContentId($entity),
                LiveNotificationController::SEVERITY_SUCCESS,
                vsprintf('<p>Downloading file %s.</p>', [
                    $entity->getFileUri(),
                ])
            );
            $data = (string)$this->getApiWrapper()->downloadFile($entity);
            $msg = vsprintf('Downloaded file for submission id = \'%s\'. Dump: %s', [$entity->getId(),
                                                                                     base64_encode($data)]);
            $this->getLogger()->debug($msg);
            LiveNotificationController::pushNotification(
                $this
                    ->getSettingsManager()
                    ->getSingleSettingsProfile($entity->getSourceBlogId())
                    ->getProjectId(),
                LiveNotificationController::getContentId($entity),
                LiveNotificationController::SEVERITY_SUCCESS,
                vsprintf('<p>Applying translation for file %s and locale %s.</p>', [
                    $entity->getFileUri(),
                    $entity->getTargetLocale(),
                ])
            );
            if ($this->acfDynamicSupport->getDefinitions() === null) {
                $this->acfDynamicSupport->run();
            }
            $this->applyXML($entity, $data, $this->xmlHelper, $this->postContentHelper);
            LiveNotificationController::pushNotification(
                $this
                    ->getSettingsManager()
                    ->getSingleSettingsProfile($entity->getSourceBlogId())
                    ->getProjectId(),
                LiveNotificationController::getContentId($entity),
                LiveNotificationController::SEVERITY_SUCCESS,
                vsprintf('<p>Completed processing for file %s and locale %s.</p>', [
                    $entity->getFileUri(),
                    $entity->getTargetLocale(),
                ])
            );
        } catch (\Exception $e) {
            if ($e instanceof SmartlingFileDownloadException) {
                $xml = $this->getXML($entity);

                if ($xml === '') {
                    $this->getLogger()->info("Detected empty xml for submissionId={$entity->getId()}, applying");
                    $this->applyXML($entity, $xml, $this->xmlHelper, $this->postContentHelper);
                    return;
                }
            }
            LiveNotificationController::pushNotification(
                $this
                    ->getSettingsManager()
                    ->getSingleSettingsProfile($entity->getSourceBlogId())
                    ->getProjectId(),
                LiveNotificationController::getContentId($entity),
                LiveNotificationController::SEVERITY_ERROR,
                vsprintf('<p>Failed downloading file %s.</p>', [
                    $entity->getFileUri(),
                ])
            );
            $msg = vsprintf(
                'Error occurred while downloading translation for submission id=\'%s\'. Message: %s.',
                [
                    $entity->getId(),
                    $e->getMessage(),
                ]
            );
            $this->getLogger()->error($msg);
        }
    }

    public function downloadTranslationBySubmissionId($id)
    {
        do_action(ExportedAPI::ACTION_SMARTLING_DOWNLOAD_TRANSLATION, $this->loadSubmissionEntityById($id));
    }

    public function downloadTranslation($contentType, $sourceBlog, $sourceEntity, $targetBlog, $targetEntity = null)
    {
        $submission = $this->getTranslationHelper()
            ->prepareSubmission($contentType, $sourceBlog, $sourceEntity, $targetBlog, $targetEntity);

        do_action(ExportedAPI::ACTION_SMARTLING_DOWNLOAD_TRANSLATION, $submission);
    }
}
