<?php

namespace Smartling\Base;

use Smartling\Submissions\SubmissionEntity;

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

            return [
                vsprintf(
                    'Translation of file %s for %s locale is locked for downloading',
                    [$entity->getFileUri(), $entity->getTargetLocale()]
                ),
            ];
        }
        if (1 === $entity->getIsCloned()) {
            $msg = vsprintf('Triggered download of cloned entity. Target Blog: %s; Target Id: %s', [
                $entity->getTargetBlogId(),
                $entity->getTargetId(),
            ]);
            $this->getLogger()->warning($msg);

            return ['There is no translation since entity is Cloned, not Translated'];
        }
        if (0 === $entity->getTargetId()) {
            //Fix for trying to download before send.
            do_action(ExportedAPI::ACTION_SMARTLING_SEND_FILE_FOR_TRANSLATION, $entity);
        }
        $messages = [];

        $data = $this->getApiWrapper()->downloadFile($entity);
        $this->getLogger()
            ->debug(vsprintf('Downloaded file for submission id = \'%s\'. Dump: %s',
                             [$entity->getId(), base64_encode($data)]));

        $this->applyXML($entity, $data);


        return $messages;
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
