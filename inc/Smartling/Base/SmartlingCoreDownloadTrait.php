<?php

namespace Smartling\Base;

use Exception;
use Smartling\Bootstrap;
use Smartling\Exception\BlogNotFoundException;
use Smartling\Exception\EntityNotFoundException;
use Smartling\Exception\InvalidXMLException;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\DateTimeHelper;
use Smartling\Helpers\EventParameters\AfterDeserializeContentEventParameters;
use Smartling\Helpers\SiteHelper;
use Smartling\Helpers\XmlEncoder;
use Smartling\Settings\ConfigurationProfileEntity;
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
        if (SubmissionEntity::SUBMISSION_STATUS_CLONED === $entity->getStatus()) {
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
        try {
            $data = $this->getApiWrapper()->downloadFile($entity);
            $this->getLogger()
                ->debug(vsprintf('Downloaded file for submission id = \'%s\'. Dump: %s',
                                 [$entity->getId(), base64_encode($data)]));
            $this->prepareFieldProcessorValues($entity);
            $translatedFields = XmlEncoder::xmlDecode($data);

            $originalData = $this->readSourceContentWithMetadataAsArray($entity);
            $translatedFields = $this->getFieldsFilter()->processStringsAfterDecoding($translatedFields);

            $translatedFields = $this->getFieldsFilter()->applyTranslatedValues($entity, $originalData, $translatedFields);

            $this->getLogger()
                ->debug(vsprintf('Deserialized translated fields for submission id = \'%s\'. Dump: %s\'.',
                                 [$entity->getId(), base64_encode(json_encode($translatedFields))]));
            if (!array_key_exists('meta', $translatedFields)) {
                $translatedFields['meta'] = [];
            }
            $targetContent = $this->getContentHelper()->readTargetContent($entity);
            $params = new AfterDeserializeContentEventParameters($translatedFields, $entity, $targetContent, $translatedFields['meta']);
            do_action(ExportedAPI::EVENT_SMARTLING_AFTER_DESERIALIZE_CONTENT, $params);
            if (array_key_exists('entity', $translatedFields) && ArrayHelper::notEmpty($translatedFields['entity'])) {
                $this->setValues($targetContent, $translatedFields['entity']);
            }
            if (100 === $entity->getCompletionPercentage()) {
                $entity->setStatus(SubmissionEntity::SUBMISSION_STATUS_COMPLETED);
                $targetContent->translationCompleted();
                $entity->setAppliedDate(DateTimeHelper::nowAsString());
            }
            $this->getContentHelper()->writeTargetContent($entity, $targetContent);
            if (array_key_exists('meta', $translatedFields) && ArrayHelper::notEmpty($translatedFields['meta'])) {
                $metaFields = &$translatedFields['meta'];
                /**
                 * @var ConfigurationProfileEntity $configurationProfile
                 */
                $configurationProfile = $this->getSettingsManager()->getSingleSettingsProfile($entity->getSourceBlogId());

                if ( 1 === $configurationProfile->getCleanMetadataOnDownload()){
                    $this->getContentHelper()->removeTargetMetadata($entity);
                }

                $this->getContentHelper()->writeTargetMetadata($entity, $metaFields);
                do_action(ExportedAPI::ACTION_SMARTLING_REGENERATE_THUMBNAILS, $entity);
            }
            $entity = $this->getSubmissionManager()->storeEntity($entity);
        } catch (InvalidXMLException $e) {
            $entity->setStatus(SubmissionEntity::SUBMISSION_STATUS_FAILED);
            $entity->setLastError('Received invalid XML file.');
            $this->getSubmissionManager()->storeEntity($entity);
            $message = vsprintf("Invalid XML file [%s] received. Submission moved to %s status.",
                                [$entity->getFileUri(), $entity->getStatus()]);
            $this->getLogger()->error($message);
            $messages[] = $message;
        } catch (EntityNotFoundException $e) {
            $entity->setStatus(SubmissionEntity::SUBMISSION_STATUS_FAILED);
            $entity->setLastError('Submission references non existent content.');
            $this->getLogger()->error($e->getMessage());
            $this->getSubmissionManager()->storeEntity($entity);
        } catch (BlogNotFoundException $e) {
            $entity->setStatus(SubmissionEntity::SUBMISSION_STATUS_FAILED);
            $entity->setLastError('Submission references non existent blog.');
            $this->getLogger()->error($e->getMessage());
            $this->getSubmissionManager()->storeEntity($entity);
            /** @var SiteHelper $sh */
            $this->handleBadBlogId($entity);
        } catch (Exception $e) {
            $messages[] = $e->getMessage();
        }

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
