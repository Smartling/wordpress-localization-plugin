<?php

namespace Smartling\Base;

use Exception;
use Smartling\Bootstrap;
use Smartling\ContentTypes\ContentTypeAttachment;
use Smartling\ContentTypes\ContentTypeNavigationMenuItem;
use Smartling\Exception\BlogNotFoundException;
use Smartling\Exception\EntityNotFoundException;
use Smartling\Exception\InvalidXMLException;
use Smartling\Exception\SmartlingFileDownloadException;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\ContentHelper;
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

            $translatedFields = $this->getFieldsFilter()
                ->applyTranslatedValues($entity, $originalData, $translatedFields);

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
                $configurationProfile = $this->getSettingsManager()
                    ->getSingleSettingsProfile($entity->getSourceBlogId());

                if (1 === $configurationProfile->getCleanMetadataOnDownload()) {
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
            $entity->setLastError('Could not apply translations because submission points to non existing content.');
            $this->getLogger()->error($e->getMessage());
            $this->getSubmissionManager()->storeEntity($entity);
        } catch (BlogNotFoundException $e) {
            $entity->setStatus(SubmissionEntity::SUBMISSION_STATUS_FAILED);
            $entity->setLastError('Could not apply translations because submission points to non existing blog.');
            $this->getLogger()->error($e->getMessage());
            $this->getSubmissionManager()->storeEntity($entity);
            /** @var SiteHelper $sh */
            $this->handleBadBlogId($entity);
        } catch (SmartlingFileDownloadException $e) {
            /**
             * Even if there is no XML file we may need rebuild target metadata.
             * May happen for attachments and menu items
             */
            if (0 < $entity->getTargetId() &&
                in_array($entity->getContentType(), [ContentTypeNavigationMenuItem::WP_CONTENT_TYPE,
                                                     ContentTypeAttachment::WP_CONTENT_TYPE], true)
            ) {
                $contentHelper = $this->getContentHelper();
                /**
                 * @var ContentHelper $contentHelper
                 */
                $currentSiteId = $contentHelper->getSiteHelper()->getCurrentSiteId();
                $sourceMetadata = $contentHelper->readSourceMetadata($entity);

                $filteredMetadata = [];

                foreach ($sourceMetadata as $key => $value) {
                    try {
                        $filteredMetadata[$key] =
                            apply_filters(ExportedAPI::FILTER_SMARTLING_METADATA_FIELD_PROCESS, $key, $value, $entity);
                    } catch (\Exception $ex) {
                        $this->getLogger()->gebug(
                            vsprintf(
                                'An error occurred while processing field %s=\'%s\' of submission id=%s. Message: %s',
                                [
                                    $key,
                                    $value,
                                    $entity->getId(),
                                    $ex->getMessage(),
                                ]
                            )
                        );

                        if ($contentHelper->getSiteHelper()->getCurrentSiteId() !== $currentSiteId) {
                            $contentHelper->getSiteHelper()->resetBlog($currentSiteId);
                        }
                    }
                }
                $diff = array_diff_assoc($sourceMetadata, $filteredMetadata);
                if (0 < count($diff)) {

                    foreach ($diff as $k => & $v) {
                        $v = [
                            'old_value' => $v,
                            'new_value' => $filteredMetadata[$k],
                        ];

                    }

                    $this->getLogger()->debug(vsprintf('Updating metadata: %s', [var_export($diff, true)]));

                    $contentHelper->writeTargetMetadata($entity, $filteredMetadata);
                }

            }
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
