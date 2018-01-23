<?php

namespace Smartling\Base;

use Exception;
use Smartling\ContentTypes\ContentTypeNavigationMenuItem;
use Smartling\Exception\BlogNotFoundException;
use Smartling\Exception\EntityNotFoundException;
use Smartling\Exception\InvalidXMLException;
use Smartling\Exception\NothingFoundForTranslationException;
use Smartling\Exception\SmartlingFileDownloadException;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\ContentHelper;
use Smartling\Helpers\DateTimeHelper;
use Smartling\Helpers\EventParameters\AfterDeserializeContentEventParameters;
use Smartling\Helpers\EventParameters\BeforeSerializeContentEventParameters;
use Smartling\Helpers\SimpleStorageHelper;
use Smartling\Helpers\SiteHelper;
use Smartling\Helpers\StringHelper;
use Smartling\Helpers\XmlEncoder;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Submissions\SubmissionEntity;


/**
 * Class SmartlingCoreUploadTrait
 * @package Smartling\Base
 */
trait SmartlingCoreUploadTrait
{
    /**
     * @param $id
     *
     * @return bool
     */
    public function sendForTranslationBySubmissionId($id)
    {
        $this->sendForTranslationBySubmission($this->loadSubmissionEntityById($id));
    }

    /**
     * @param SubmissionEntity $submission
     *
     * @return SubmissionEntity
     */
    private function renewContentHash(SubmissionEntity $submission)
    {
        $content = $this->getContentHelper()->readSourceContent($submission);
        $newHash = $this->getContentSerializationHelper()->calculateHash($submission);
        $submission->setSourceContentHash($newHash);
        $submission->setOutdated(0);
        $submission->setSourceTitle($content->getTitle());
        $submission = $this->getSubmissionManager()->storeEntity($submission);

        return $submission;
    }

    /**
     * @param SubmissionEntity $submission
     *
     * @return array
     */
    private function readSourceContentWithMetadataAsArray(SubmissionEntity $submission)
    {
        $source = [
            'entity' => $this->getContentHelper()->readSourceContent($submission)->toArray(),
            'meta'   => $this->getContentHelper()->readSourceMetadata($submission),
        ];

        $source['meta'] = $source['meta'] ? : [];

        return $source;
    }

    /**
     * @param SubmissionEntity $submission
     */
    public function cloneWithoutTranslation(SubmissionEntity $submission)
    {
        $this->getLogger()->debug(
            vsprintf(
                'Preparing to clone submission id = \'%s\' (blog = \'%s\', content = \'%s\', type = \'%s\').',
                [
                    $submission->getId(),
                    $submission->getSourceBlogId(),
                    $submission->getSourceId(),
                    $submission->getContentType(),
                ]
            )
        );

        try {
            if (null === $submission->getId()) {
                $submission->getFileUri();
            }

            $submission = $this->renewContentHash($submission);
            $submission = $this->prepareTargetEntity($submission, true);
            $submission->setIsCloned(1);
            $this->getSubmissionManager()->storeEntity($submission);
        } catch (EntityNotFoundException $e) {
            $this->getLogger()->error($e->getMessage());
            $this->getSubmissionManager()->setErrorMessage($submission, 'Submission references non existent content.');
        } catch (BlogNotFoundException $e) {
            $this->getSubmissionManager()->setErrorMessage($submission, 'Submission references non existent blog.');
            $this->handleBadBlogId($submission);
        } catch (Exception $e) {
            $this->getSubmissionManager()
                ->setErrorMessage($submission, vsprintf('Error occurred: %s', [$e->getMessage()]));
            $this->getLogger()->error($e->getMessage());
        }
    }

    /**
     * Processes content by submission and returns only XML string for translation
     *
     * @param SubmissionEntity $submission
     *
     * @return string
     * @throws NothingFoundForTranslationException
     */
    public function getXMLFiltered(SubmissionEntity $submission)
    {
        $this->getLogger()->debug(
            vsprintf(
                'Preparing to generate XML for submission id = \'%s\' (blog = \'%s\', content = \'%s\', type = \'%s\').',
                [
                    $submission->getId(),
                    $submission->getSourceBlogId(),
                    $submission->getSourceId(),
                    $submission->getContentType(),
                ]
            )
        );

        try {
            if (null === $submission->getId()) {
                // generate URI
                $submission->getFileUri();
                $submission = $this->getSubmissionManager()->storeEntity($submission);
            }

            $submission = apply_filters(ExportedAPI::FILTER_SMARTLING_PREPARE_TARGET_CONTENT, $submission);
            $submission = $this->renewContentHash($submission);

            $source = $this->readSourceContentWithMetadataAsArray($submission);

            $contentEntity = $this->getContentHelper()->readSourceContent($submission);
            $params = new BeforeSerializeContentEventParameters($source, $submission, $contentEntity, $source['meta']);
            do_action(ExportedAPI::EVENT_SMARTLING_BEFORE_SERIALIZE_CONTENT, $params);
            $source = $params->getPreparedFields();
            $this->prepareFieldProcessorValues($submission);
            $filteredValues = $this->getFieldsFilter()->processStringsBeforeEncoding($submission, $source);

            if (is_array($filteredValues) && 0 === count($filteredValues)) {
                $message = vsprintf(
                    'Prepared Submission = \'%s\' has nothing to translate. Setting status to \'%s\'.',
                    [
                        $submission->getId(),
                        SubmissionEntity::SUBMISSION_STATUS_FAILED,
                    ]
                );
                $this->getLogger()->warning($message);
                $submission->setBatchUid('');
                $submission = $this->getSubmissionManager()
                    ->setErrorMessage($submission, 'There is no original content for translation.');

                throw new NothingFoundForTranslationException($message);
            } else {
                $this->prepareFieldProcessorValues($submission);
                $xml = XmlEncoder::xmlEncode($filteredValues, $source, $submission);
                $this->getLogger()->debug(vsprintf('Serialized fields to XML: %s', [base64_encode($xml),]));

                return $xml;
            }
        } catch (EntityNotFoundException $e) {
            $this->getLogger()->error($e->getMessage());
            $this->getSubmissionManager()->setErrorMessage($submission, 'Submission references non existent content.');
        } catch (BlogNotFoundException $e) {
            $this->getSubmissionManager()->setErrorMessage($submission, 'Submission references non existent blog.');
            $this->handleBadBlogId($submission);
        } catch (NothingFoundForTranslationException $e) {
            return '';
        } catch (Exception $e) {
            $this->getSubmissionManager()
                ->setErrorMessage($submission, vsprintf('Error occurred: %s', [$e->getMessage()]));
            $this->getLogger()->error($e->getMessage());
        }
    }

    public function applyXML(SubmissionEntity $submission, $xml)
    {
        $messages = [];
        try {
            $this->prepareFieldProcessorValues($submission);
            if ('' === $xml) {
                $translation = [];
            } else {
                $translation = XmlEncoder::xmlDecode($xml, $submission);
            }
            $original = $this->readSourceContentWithMetadataAsArray($submission);
            $translation = $this->getFieldsFilter()->processStringsAfterDecoding($translation);
            $translation = $this->getFieldsFilter()->applyTranslatedValues($submission, $original, $translation);

            $this->getLogger()
                ->debug(vsprintf('Deserialized translated fields for submission id = \'%s\'. Dump: %s\'.', [$submission->getId(),
                                                                                                            base64_encode(json_encode($translation))]));
            if (!array_key_exists('meta', $translation)) {
                $translation['meta'] = [];
            }
            $targetContent = $this->getContentHelper()->readTargetContent($submission);
            $params = new AfterDeserializeContentEventParameters($translation, $submission, $targetContent, $translation['meta']);
            do_action(ExportedAPI::EVENT_SMARTLING_AFTER_DESERIALIZE_CONTENT, $params);
            if (array_key_exists('entity', $translation) && ArrayHelper::notEmpty($translation['entity'])) {
                $this->setValues($targetContent, $translation['entity']);
            }
            /**
             * @var ConfigurationProfileEntity $configurationProfile
             */
            $configurationProfile = $this->getSettingsManager()
                ->getSingleSettingsProfile($submission->getSourceBlogId());

            if (100 === $submission->getCompletionPercentage()) {
                $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_COMPLETED);
                if (1 == $configurationProfile->getPublishCompleted()) {
                    $targetContent->translationCompleted();
                }
                $submission->setAppliedDate(DateTimeHelper::nowAsString());
            }
            $this->getContentHelper()->writeTargetContent($submission, $targetContent);
            if (array_key_exists('meta', $translation) && ArrayHelper::notEmpty($translation['meta'])) {
                $metaFields = &$translation['meta'];

                if (1 === $configurationProfile->getCleanMetadataOnDownload()) {
                    $this->getContentHelper()->removeTargetMetadata($submission);
                }

                $this->getContentHelper()->writeTargetMetadata($submission, $metaFields);
                do_action(ExportedAPI::ACTION_SMARTLING_SYNC_MEDIA_ATTACHMENT, $submission);
            }
            $submission = $this->getSubmissionManager()->storeEntity($submission);
        } catch (InvalidXMLException $e) {
            $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_FAILED);
            $submission->setLastError('Received invalid XML file.');
            $this->getSubmissionManager()->storeEntity($submission);
            $message = vsprintf("Invalid XML file [%s] received. Submission moved to %s status.", [$submission->getFileUri(),
                                                                                                   $submission->getStatus()]);
            $this->getLogger()->error($message);
            $messages[] = $message;
        } catch (EntityNotFoundException $e) {
            $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_FAILED);
            $submission->setLastError('Could not apply translations because submission points to non existing content.');
            $this->getLogger()->error($e->getMessage());
            $this->getSubmissionManager()->storeEntity($submission);
        } catch (BlogNotFoundException $e) {
            $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_FAILED);
            $submission->setLastError('Could not apply translations because submission points to non existing blog.');
            $this->getLogger()->error($e->getMessage());
            $this->getSubmissionManager()->storeEntity($submission);
            /** @var SiteHelper $sh */
            $this->handleBadBlogId($submission);
        } catch (SmartlingFileDownloadException $e) {
            /**
             * Even if there is no XML file we may need rebuild target metadata.
             * May happen for attachments and menu items
             */
            $customTypes = [ContentTypeNavigationMenuItem::WP_CONTENT_TYPE, 'attachment'];
            if (0 < $submission->getTargetId() && in_array($submission->getContentType(), $customTypes, true)) {
                $contentHelper = $this->getContentHelper();
                /**
                 * @var ContentHelper $contentHelper
                 */
                $currentSiteId = $contentHelper->getSiteHelper()->getCurrentSiteId();
                $sourceMetadata = $contentHelper->readSourceMetadata($submission);

                $filteredMetadata = [];

                foreach ($sourceMetadata as $key => $value) {
                    try {
                        $filteredMetadata[$key] =
                            apply_filters(ExportedAPI::FILTER_SMARTLING_METADATA_FIELD_PROCESS, $key, $value, $submission);
                    } catch (\Exception $ex) {
                        $this->getLogger()->gebug(
                            vsprintf(
                                'An error occurred while processing field %s=\'%s\' of submission id=%s. Message: %s',
                                [
                                    $key,
                                    $value,
                                    $submission->getId(),
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

                    $contentHelper->writeTargetMetadata($submission, $filteredMetadata);
                }

            }
        } catch (Exception $e) {
            $messages[] = $e->getMessage();
        }

        return $messages;
    }

    /**
     * @param SubmissionEntity $submission
     */
    public function bulkSubmit(SubmissionEntity $submission)
    {
        try {
            $xml = '';

            $params = [
                'status'    => [SubmissionEntity::SUBMISSION_STATUS_NEW],
                'file_uri'  => $submission->getFileUri(),
                'is_cloned' => [0],
                'is_locked' => [0],
            ];

            if (!StringHelper::isNullOrEmpty($submission->getBatchUid())) {
                $params['batch_uid'] = [$submission->getBatchUid()];
            }

            /**
             * Looking for other locales to pass filters and create placeholders.
             */
            $submissions = $this->getSubmissionManager()->find($params);

            $locales = [];

            $xml = $this->getXMLFiltered($submission);

            foreach ($submissions as $_submission) {
                /**
                 * If submission still doesn't have file URL - create it
                 */
                $submissionFields = $_submission->toArray(false);
                if (StringHelper::isNullOrEmpty($submissionFields['file_uri'])) {
                    // Generating fileUri
                    $_submission->getFileUri();
                    $_submission = $this->getSubmissionManager()->storeEntity($_submission);
                }
                unset ($submissionFields);
                // Passing filters
                $xml = $this->getXMLFiltered($_submission);
                // Processing attachments
                do_action(ExportedAPI::ACTION_SMARTLING_SYNC_MEDIA_ATTACHMENT, $_submission);
                // Preparing placeholders
                $this->prepareRelatedSubmissions($_submission);

                $locales[] = $this->getSettingsManager()->getSmartlingLocaleBySubmission($_submission);
            }

            if (!StringHelper::isNullOrEmpty($xml)) {
                if ($this->sendFile($submission, $xml, $locales)) {
                    foreach ($submissions as $_submission) {
                        $_submission->setBatchUid('');
                        $_submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS);
                    }
                } else {
                    foreach ($submissions as $_submission) {
                        $_submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_FAILED);
                    }
                }
                $this->getSubmissionManager()->storeSubmissions($submissions);
            }

            $this->executeBatch($submission->getBatchUid(), $submission->getSourceBlogId());
        } catch (\Exception $e) {
            $proceedAuthException = function ($e) use (& $proceedAuthException) {
                if (401 == $e->getCode()) {
                    $this->getLogger()->error(vsprintf('Invalid credentials. Check profile settings.', []));
                } elseif ($e->getPrevious() instanceof \Exception) {
                    $proceedAuthException($e->getPrevious());
                }
            };
            $proceedAuthException($e);
            $this->getLogger()->error($e->getMessage());
            $this->getSubmissionManager()
                ->setErrorMessage($submission, vsprintf('Could not submit because: %s', [$e->getMessage()]));
        }
    }

    /**
     * @param $batchUid
     * @param int $sourceBlogId
     */
    private function executeBatch($batchUid, $sourceBlogId)
    {
        try {
            $submissions = $this->getSubmissionManager()->searchByBatchUid($batchUid);

            if (0 === count($submissions)) {
                $profile = $this->getSettingsManager()->getSingleSettingsProfile($sourceBlogId);

                $this->getApiWrapper()->executeBatch($profile, $batchUid);

                $msg = vsprintf('Batch \'%s\' executed', [$batchUid]);
                $this->getLogger()->debug($msg);
            }
        } catch (Exception $e) {
            $msg = vsprintf('Error executing batch \'%s\'. Message: \'%s\'', [$batchUid, $e->getMessage()]);
            $this->getLogger()->error($msg);
        }
    }

    /**
     * @param SubmissionEntity $submission
     *
     * @return bool
     */
    public function sendForTranslationBySubmission(SubmissionEntity $submission)
    {
        if (1 === $submission->getIsLocked()) {
            $this->getLogger()
                ->debug(vsprintf('Requested re-upload of protected submission id=%s. Skipping.', [$submission->getId()]));

            return;
        }

        $this->getLogger()->debug(
            vsprintf(
                'Preparing to send submission id = \'%s\' (blog = \'%s\', content = \'%s\', type = \'%s\', job = \'%s\').',
                [
                    $submission->getId(),
                    $submission->getSourceBlogId(),
                    $submission->getSourceId(),
                    $submission->getContentType(),
                    $submission->getBatchUid(),
                ]
            )
        );

        try {
            if (1 === $submission->getIsCloned()) {
                $xml = $this->getXMLFiltered($submission);
                $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS);
                $submission = $this->getSubmissionManager()->storeEntity($submission);
                $this->applyXML($submission, $xml);
            }

            $this->bulkSubmit($submission);
        } catch (EntityNotFoundException $e) {
            $this->getLogger()->error($e->getMessage());
            $this->getSubmissionManager()->setErrorMessage($submission, 'Submission references non existent content.');
        } catch (BlogNotFoundException $e) {
            $this->getSubmissionManager()->setErrorMessage($submission, 'Submission references non existent blog.');
            $this->handleBadBlogId($submission);
        } catch (Exception $e) {
            $this->getSubmissionManager()
                ->setErrorMessage($submission, vsprintf('Error occurred: %s', [$e->getMessage()]));
            $this->getLogger()->error($e->getMessage());
        }
    }

    public function getOrPrepareSubmission($contentType, $sourceBlogId, $sourceEntityId, $targetBlogId, $status = null)
    {
        $status = $status ? : SubmissionEntity::SUBMISSION_STATUS_NEW;

        /**
         * @var SubmissionEntity $submission
         */
        $submission = $this->getTranslationHelper()
            ->prepareSubmissionEntity($contentType, $sourceBlogId, $sourceEntityId, $targetBlogId);

        $contentEntity = $this->getContentHelper()->readSourceContent($submission);

        if (null === $submission->getId()) {
            $submission->setSourceContentHash('');
            $submission->setSourceTitle($contentEntity->getTitle());

            // generate URI
            $submission->getFileUri();
            $submission = $this->getSubmissionManager()->storeEntity($submission);
        } else {
            if (0 === $submission->getIsLocked()) {
                $submission->setStatus($status);
                $submission->setLastError('');
            }
        }

        return $this->getSubmissionManager()->storeEntity($submission);
    }

    /**
     * @param string   $contentType
     * @param int      $sourceBlog
     * @param int      $sourceEntity
     * @param int      $targetBlog
     * @param int|null $targetEntity
     * @param bool     $clone
     *
     * @return bool
     */
    public function createForTranslation($contentType, $sourceBlog, $sourceEntity, $targetBlog, $targetEntity = null, $clone = false)
    {
        /**
         * @var SubmissionEntity $submission
         */
        $submission = $this->getTranslationHelper()
            ->prepareSubmissionEntity($contentType, $sourceBlog, $sourceEntity, $targetBlog, $targetEntity);

        $contentEntity = $this->getContentHelper()->readSourceContent($submission);

        if (null === $submission->getId()) {
            $submission->setSourceContentHash('');
            $submission->setSourceTitle($contentEntity->getTitle());

            // generate URI
            $submission->getFileUri();
        } else {
            if (0 === $submission->getIsLocked()) {
                $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_NEW);
            } else {
                $this->getLogger()
                    ->debug(vsprintf('Requested re-upload of protected submission id=%s. Skipping.', [$submission->getId()]));
            }
        }

        $isCloned = true === $clone ? 1 : 0;
        $submission->setIsCloned($isCloned);

        return $this->getSubmissionManager()->storeEntity($submission);
    }
}