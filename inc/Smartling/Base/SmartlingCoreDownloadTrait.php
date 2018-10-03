<?php

namespace Smartling\Base;

use Smartling\Submissions\SubmissionEntity;
use Smartling\WP\Controller\LiveNotificationController;

/**
 * Class SmartlingCoreDownloadTrait
 * @package Smartling\Base
 */
trait SmartlingCoreDownloadTrait
{
    public function downloadTranslationBySubmission(SubmissionEntity $entity)
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
            //Fix for trying to download before send.
            do_action(ExportedAPI::ACTION_SMARTLING_SEND_FILE_FOR_TRANSLATION, $entity);
        }

        try {
            LiveNotificationController::pushNotification(
                $this
                    ->getSettingsManager()
                    ->getSingleSettingsProfile($entity->getSourceBlogId())
                    ->getProjectId(),
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
                LiveNotificationController::SEVERITY_SUCCESS,
                vsprintf('<p>Applying translation for file %s and locale %s.</p>', [
                    $entity->getFileUri(),
                    $entity->getTargetLocale(),
                ])
            );
            $this->applyXML($entity, $data);
        } catch (\Exception $e) {
            LiveNotificationController::pushNotification(
                $this
                    ->getSettingsManager()
                    ->getSingleSettingsProfile($entity->getSourceBlogId())
                    ->getProjectId(),
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
