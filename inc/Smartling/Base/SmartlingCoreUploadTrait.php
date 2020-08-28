<?php

namespace Smartling\Base;

use Exception;
use Smartling\ContentTypes\ContentTypeNavigationMenuItem;
use Smartling\Exception\BlogNotFoundException;
use Smartling\Exception\EntityNotFoundException;
use Smartling\Exception\InvalidXMLException;
use Smartling\Exception\NothingFoundForTranslationException;
use Smartling\Exception\SmartlingFileDownloadException;
use Smartling\Exception\SmartlingTargetPlaceholderCreationFailedException;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\ContentHelper;
use Smartling\Helpers\DateTimeHelper;
use Smartling\Helpers\EventParameters\AfterDeserializeContentEventParameters;
use Smartling\Helpers\EventParameters\BeforeSerializeContentEventParameters;
use Smartling\Helpers\SiteHelper;
use Smartling\Helpers\StringHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Helpers\XmlEncoder;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Submissions\SubmissionEntity;
use Smartling\WP\Controller\LiveNotificationController;


/**
 * Class SmartlingCoreUploadTrait
 * @package Smartling\Base
 */
trait SmartlingCoreUploadTrait
{
    /**
     * @param $id
     *
     * @return void
     * @throws \Smartling\Exception\SmartlingDbException
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

    protected function getFunctionProxyHelper() {
        return new WordpressFunctionProxyHelper();
    }

    /**
     * Processes content by submission and returns only XML string for translation
     *
     * @param SubmissionEntity $submission
     *
     * @return string
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

            $submission = $this
                ->getFunctionProxyHelper()
                ->apply_filters(ExportedAPI::FILTER_SMARTLING_PREPARE_TARGET_CONTENT, $submission);

            /**
             * Creating of target placeholder has failed
             */
            if (SubmissionEntity::SUBMISSION_STATUS_FAILED === $submission->getStatus()) {
                /**
                 * @var SubmissionEntity $submission
                 */
                $msg = vsprintf(
                    'Failed creating target placeholder for submission id=\'%s\', source_blog_id=\'%s\', source_id=\'%s\', target_blog_id=\'%s\' with message: \'%s\'',
                    [
                        $submission->getId(),
                        $submission->getSourceBlogId(),
                        $submission->getSourceId(),
                        $submission->getTargetId(),
                        $submission->getLastError(),
                    ]
                );
                $this->getLogger()->error($msg);
                throw new SmartlingTargetPlaceholderCreationFailedException($msg);
            }

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
                $xml = XmlEncoder::xmlEncode($filteredValues, $submission, $source);

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
            throw $e;
        }
    }

    /**
     * @param SubmissionEntity $submission
     *
     * @return array
     */
    private function readLockedTranslationFieldsBySubmission(SubmissionEntity $submission)
    {
        $this->getLogger()
            ->debug(vsprintf('Starting loading locked fields for submission id=%s', [$submission->getId()]));

        $lockedData = [
            'entity' => [],
            'meta'   => [],
        ];

        if (0 === (int)$submission->getTargetId()) {
            /**
             * there is still no translation or placeholder
             */
            return $lockedData;
        }

        $lockedFields = maybe_unserialize($submission->getLockedFields());
        $lockedFields = (!is_array($lockedFields)) ? [] : $lockedFields;

        $targetContent = $this->getContentHelper()->readTargetContent($submission)->toArray(false);
        $targetMeta = $this->getContentHelper()->readTargetMetadata($submission);

        $this->getLogger()->debug(vsprintf('Got target metadata: %s.', [var_export($targetMeta, true)]));

        foreach ($lockedFields as $lockedFieldName) {

            if (preg_match('/^meta\//ius', $lockedFieldName)) {
                $_fieldName = preg_replace('/^meta\//ius', '', $lockedFieldName);
                $this->getLogger()->debug(vsprintf('Got field \'%s\'', [$_fieldName]));
                if (array_key_exists($_fieldName, $targetMeta)) {
                    $lockedData['meta'][$_fieldName] = $targetMeta[$_fieldName];
                }
            } elseif (preg_match('/^entity\//ius', $lockedFieldName)) {
                $_fieldName = preg_replace('/^entity\//ius', '', $lockedFieldName);
                if (array_key_exists($_fieldName, $targetContent)) {
                    $lockedData['entity'][$_fieldName] = $targetContent[$_fieldName];
                }
            } else {
                $this->getLogger()->debug(vsprintf('Got strange unknown field \'%s\'', [$lockedFieldName]));
            }
        }

        return $lockedData;
    }

    private static function arrayMergeIfKeyNotExists($lockedData, $translation)
    {
        foreach ($lockedData as $lockedDatumKey => $lockedDatum) {
            $translation[$lockedDatumKey] = $lockedDatum;
        }

        return $translation;
    }

    /**
     * @param SubmissionEntity $submission
     * @param string $xml
     * @return array
     */
    public function applyXML(SubmissionEntity $submission, $xml)
    {
        $messages = [];
        try {

            $lockedData = $this->readLockedTranslationFieldsBySubmission($submission);

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
                $translation['entity'] = self::arrayMergeIfKeyNotExists($lockedData['entity'], $translation['entity']);
                $this->setValues($targetContent, $translation['entity']);
            }
            /**
             * @var ConfigurationProfileEntity $configurationProfile
             */
            $configurationProfile = $this->getSettingsManager()
                ->getSingleSettingsProfile($submission->getSourceBlogId());


            $percentage = $submission->getCompletionPercentage();
            $this->getLogger()->debug(vsprintf('Current percentage is %s', [$percentage]));

            if (100 === $percentage) {
                $this->getLogger()->debug(
                    vsprintf(
                        'Submission id=%s (blog=%s, item=%s, content-type=%s) has %s%% completion. Setting status %s.',
                        [
                            $submission->getId(),
                            $submission->getSourceBlogId(),
                            $submission->getSourceId(),
                            $submission->getContentType(),
                            $submission->getCompletionPercentage(),
                            SubmissionEntity::SUBMISSION_STATUS_COMPLETED,
                        ]
                    )
                );
                $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_COMPLETED);
                if (1 == $configurationProfile->getPublishCompleted()) {

                    $this->getLogger()->debug(
                        vsprintf(
                            'Submission id=%s (blog=%s, item=%s, content-type=%s) setting status %s for translation. Profile snapshot: %s',
                            [
                                $submission->getId(),
                                $submission->getSourceBlogId(),
                                $submission->getSourceId(),
                                $submission->getContentType(),
                                'publish',
                                base64_encode(serialize($configurationProfile->toArray(false))),
                            ]
                        )
                    );
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
                $metaFields = self::arrayMergeIfKeyNotExists($lockedData['meta'], $metaFields);
                $this->getContentHelper()->writeTargetMetadata($submission, $metaFields);
                do_action(ExportedAPI::ACTION_SMARTLING_SYNC_MEDIA_ATTACHMENT, $submission);
            }
            $submission = $this->getSubmissionManager()->storeEntity($submission);

            $this->prepareRelatedSubmissions($submission);
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
            $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_FAILED);
            $submission->setLastError($e->getMessage());
            $this->getSubmissionManager()->storeEntity($submission);
            $this->getLogger()->error($e->getMessage());
            $messages[] = $e->getMessage();
        }

        return $messages;
    }

    /**
     * @param SubmissionEntity $submission
     */
    public function bulkSubmit(SubmissionEntity $submission)
    {
        $submissionHasBatchUid = !StringHelper::isNullOrEmpty($submission->getBatchUid());
        try {
            $xml = $this->getXMLFiltered($submission);
            $submission = $this->getSubmissionManager()->storeEntity($submission);
            /**
             * @var SubmissionEntity $submission
             */
            $params = [
                SubmissionEntity::FIELD_STATUS          => [SubmissionEntity::SUBMISSION_STATUS_NEW],
                SubmissionEntity::FIELD_FILE_URI        => $submission->getFileUri(),
                SubmissionEntity::FIELD_IS_CLONED       => [0],
                SubmissionEntity::FIELD_IS_LOCKED       => [0],
                SubmissionEntity::FIELD_TARGET_BLOG_ID  => $this->getSettingsManager()
                                                                ->getProfileTargetBlogIdsByMainBlogId($submission->getSourceBlogId()),
            ];

            if ($submissionHasBatchUid) {
                $params[SubmissionEntity::FIELD_BATCH_UID] = [$submission->getBatchUid()];
            }

            /**
             * Looking for other locales to pass filters and create placeholders.
             */
            $submissions = $this->getSubmissionManager()->find($params);

            $locales = [];

            foreach ($submissions as $_submission) {
                /**
                 * If submission still doesn't have file URL - create it
                 */
                $submissionFields = $_submission->toArray(false);
                if (StringHelper::isNullOrEmpty($submissionFields[SubmissionEntity::FIELD_FILE_URI])) {
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

            $this->getLogger()->debug("Sending file {$submission->getFileUri()}");

            if (!StringHelper::isNullOrEmpty($xml)) {
                LiveNotificationController::pushNotification(
                    $this
                        ->getSettingsManager()
                        ->getSingleSettingsProfile($submission->getSourceBlogId())
                        ->getProjectId(),
                    LiveNotificationController::getContentId($submission),
                    LiveNotificationController::SEVERITY_SUCCESS,
                    vsprintf('<p>Sending file %s for locales %s.</p>', [
                        $submission->getFileUri(),
                        implode(',', array_values($locales)),
                    ])
                );
                if ($this->sendFile($submission, $xml, $locales)) {
                    $this->getLogger()->debug("File {$submission->getFileUri()} sent");
                    LiveNotificationController::pushNotification(
                        $this
                            ->getSettingsManager()
                            ->getSingleSettingsProfile($submission->getSourceBlogId())
                            ->getProjectId(),
                        LiveNotificationController::getContentId($submission),
                        LiveNotificationController::SEVERITY_SUCCESS,
                        vsprintf('<p>Sent file %s for locales %s.</p>', [
                            $submission->getFileUri(),
                            implode(',', $locales),
                        ])
                    );
                    foreach ($submissions as $_submission) {
                        $_submission->setBatchUid('');
                        $_submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS);
                    }
                } else {
                    $this->getLogger()->error("File {$submission->getFileUri()} failed");
                    LiveNotificationController::pushNotification(
                        $this
                            ->getSettingsManager()
                            ->getSingleSettingsProfile($submission->getSourceBlogId())
                            ->getProjectId(),
                        LiveNotificationController::getContentId($submission),
                        LiveNotificationController::SEVERITY_ERROR,
                        vsprintf('<p>Failed sending file %s for locales %s.</p>', [
                            $submission->getFileUri(),
                            implode(',', $locales),
                        ])
                    );
                    foreach ($submissions as $_submission) {
                        $_submission->setBatchUid('');
                        $_submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_FAILED);
                    }
                }
                $this->getSubmissionManager()->storeSubmissions($submissions);
            }

            $this->executeBatch($submission->getBatchUid(), $submission->getSourceBlogId());
        } catch (\Exception $e) {
            $caught = $e;
            do {
                if (401 === $e->getCode()) {
                    $this->getLogger()->error('Invalid credentials. Check profile settings.');
                    break;
                }
                if ($submissionHasBatchUid
                    && strpos("Batch is not suitable for adding files", $e->getMessage()) !== false) {
                    $this->getLogger()->error("Batch {$submission->getBatchUid()} is not suitable for adding files");
                    $submissions = $this->getSubmissionManager()->find([
                        SubmissionEntity::FIELD_STATUS => [SubmissionEntity::SUBMISSION_STATUS_NEW],
                        SubmissionEntity::FIELD_BATCH_UID => [$submission->getBatchUid()],
                    ]);
                    foreach ($submissions as $found) {
                        /** @var SubmissionEntity $found */
                        $found->setBatchUid('');
                        $found->setStatus(SubmissionEntity::SUBMISSION_STATUS_FAILED);
                        $this->getLogger()->notice("Setting submission {$found->getId()} status to failed");
                    }
                    $this->getSubmissionManager()->storeSubmissions($submissions);
                    break;
                }
                $e = $e->getPrevious();
            } while ($e !== null);
            $e = $caught;
            $this->getLogger()->error($e->getMessage());
            $this->getSubmissionManager()
                ->setErrorMessage($submission, vsprintf('Could not submit because: %s', [$e->getMessage()]));

            LiveNotificationController::pushNotification(
                $this
                    ->getSettingsManager()
                    ->getSingleSettingsProfile($submission->getSourceBlogId())
                    ->getProjectId(),
                LiveNotificationController::getContentId($submission),
                LiveNotificationController::SEVERITY_ERROR,
                vsprintf('<p>Failed sending file %s.</p>', [
                    $submission->getFileUri(),
                ])
            );
        }
    }

    private function closeBatch($batchUid)
    {
        $params = [
            SubmissionEntity::FIELD_STATUS => [SubmissionEntity::SUBMISSION_STATUS_NEW],
            SubmissionEntity::FIELD_BATCH_UID => [$batchUid],
        ];

        $submissions = $this->getSubmissionManager()->find($params);
        $count = count($submissions);
        if ($count > 0) {
            $this->getLogger()->warning("Found $count new submissions with batchUid=$batchUid while closing batch");
        }
        foreach ($submissions as $submission) {
            /**
             * @var SubmissionEntity $submission
             */
            $submission->setBatchUid('');
        }
        $this->getSubmissionManager()->storeSubmissions($submissions);
    }

    /**
     * @param     $batchUid
     * @param int $sourceBlogId
     */
    private function executeBatch($batchUid, $sourceBlogId)
    {
        $msg = vsprintf('Preparing to start batch "%s" execution...', [$batchUid]);
        $this->getLogger()->debug($msg);
        try {
            $submissions = $this->getSubmissionManager()->searchByBatchUid($batchUid);

            if (0 === count($submissions)) {
                $profile = $this->getSettingsManager()->getSingleSettingsProfile($sourceBlogId);

                $this->getApiWrapper()->executeBatch($profile, $batchUid);

                $msg = vsprintf('Batch "%s" executed', [$batchUid]);
                $this->getLogger()->debug($msg);
                $this->closeBatch($batchUid);
            }
        } catch (Exception $e) {
            $msg = vsprintf('Error executing batch "%s". Message: "%s"', [$batchUid, $e->getMessage()]);
            $this->getLogger()->error($msg);
        }
    }

    /**
     * @param SubmissionEntity $submission
     * @return SubmissionEntity
     * @throws Exception
     */
    private function fixSubmissionBatchUid(SubmissionEntity $submission)
    {
        $submissionDump = base64_encode(serialize($submission->toArray(false)));

        $this
            ->getLogger()
            ->info(
                vsprintf(
                    'Got submission \'%s\' without batchUid. Trying to get batchUid. Original trace:\n%s ',
                    [
                        $submissionDump,
                        (new \Exception())->getTraceAsString()
                    ]
                )
            );

        try {
            $profile = $this
                ->getSettingsManager()
                ->getSingleSettingsProfile($submission->getSourceBlogId());

            $batchUid = $this
                ->getApiWrapper()
                ->retrieveBatchForBucketJob($profile, (bool)$profile->getAutoAuthorize());

            $submission->setBatchUid($batchUid);
            $submission = $this->getSubmissionManager()->storeEntity($submission);
        } catch (\Exception $e) {
            $msg = vsprintf(
                'Failed getting batchUid for submission \'%s\'. Message: %s',
                [$submissionDump, $e->getMessage(),]
            );
            $submission->setLastError('Cannot upload without BatchUid. Manual reupload needed.');
            $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_FAILED);
            $this->getLogger()->warning($msg);
            $this->getSubmissionManager()->storeEntity($submission);
            throw $e;
        }

        return $submission;
    }

    /**
     * @param SubmissionEntity $submission
     *
     * @return void
     */
    public function sendForTranslationBySubmission(SubmissionEntity $submission)
    {
        if (1 === $submission->getIsLocked()) {
            $this->getLogger()
                ->debug(vsprintf('Requested re-upload of protected submission id=%s. Skipping.', [$submission->getId()]));

            return;
        }

        /**
         * @var ConfigurationProfileEntity $configurationProfile
         */
        $configurationProfile = $this->getSettingsManager()->getSingleSettingsProfile($submission->getSourceBlogId());

        // Mark attachment submission as "Cloned" if there is "Clone attachment"
        // option is enabled in configuration profile.
        if (1 === $configurationProfile->getCloneAttachment() && $submission->getContentType() == 'attachment') {
            $submission->setIsCloned(1);
            $submission = $this->getSubmissionManager()->storeEntity($submission);

            $this->getLogger()->info(
                vsprintf(
                    'Attachment submission id="%s" marked as cloned (blog="%s", content="%s", type="%s", batch="%s").',
                    [
                        $submission->getId(),
                        $submission->getSourceBlogId(),
                        $submission->getSourceId(),
                        $submission->getContentType(),
                        $submission->getBatchUid(),
                    ]
                )
            );
        }

        $this->getLogger()->debug(
            vsprintf(
                'Preparing to send submission id="%s" (blog="%s", content="%s", type="%s", batch="%s").',
                [
                    $submission->getId(),
                    $submission->getSourceBlogId(),
                    $submission->getSourceId(),
                    $submission->getContentType(),
                    $submission->getBatchUid(),
                ]
            )
        );

        LiveNotificationController::pushNotification(
            $configurationProfile->getProjectId(),
            LiveNotificationController::getContentId($submission),
            LiveNotificationController::SEVERITY_SUCCESS,
            vsprintf('<p>Processing upload queue.<br/>Uploading %s %s in blog %s.</p>', [
                $submission->getContentType(),
                $submission->getSourceId(),
                $submission->getSourceBlogId(),
            ])
        );

        try {
            if (1 === $submission->getIsCloned()) {
                $this->prepareRelatedSubmissions($submission);
                $xml = $this->getXMLFiltered($submission);
                $submission->getFileUri();
                $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS);
                $submission = $this->getSubmissionManager()->storeEntity($submission);
                $this->applyXML($submission, $xml);

                LiveNotificationController::pushNotification(
                    $configurationProfile->getProjectId(),
                    LiveNotificationController::getContentId($submission),
                    LiveNotificationController::SEVERITY_SUCCESS,
                    vsprintf('<p>Cloned %s %s in blog %s.</p>', [
                        $submission->getContentType(),
                        $submission->getSourceId(),
                        $submission->getSourceBlogId(),
                    ])
                );

            } else {
                if (empty(trim($submission->getBatchUid()))) {
                    $submission = $this->fixSubmissionBatchUid($submission);
                }

                $this->bulkSubmit($submission);
            }
        } catch (EntityNotFoundException $e) {
            $this->getLogger()->error($e->getMessage());
            $this->getSubmissionManager()->setErrorMessage($submission, 'Submission references non existent content.');

            LiveNotificationController::pushNotification(
                $configurationProfile->getProjectId(),
                LiveNotificationController::getContentId($submission),
                LiveNotificationController::SEVERITY_ERROR,
                vsprintf('<p>Failed processing %s %s in blog %s.</p>', [
                    $submission->getContentType(),
                    $submission->getSourceId(),
                    $submission->getSourceBlogId(),
                ])
            );
        } catch (BlogNotFoundException $e) {
            $this->getSubmissionManager()->setErrorMessage($submission, 'Submission references non existent blog.');

            LiveNotificationController::pushNotification(
                $configurationProfile->getProjectId(),
                LiveNotificationController::getContentId($submission),
                LiveNotificationController::SEVERITY_ERROR,
                vsprintf('<p>Failed processing %s %s in blog %s.</p>', [
                    $submission->getContentType(),
                    $submission->getSourceId(),
                    $submission->getSourceBlogId(),
                ])
            );

            $this->handleBadBlogId($submission);

        } catch (Exception $e) {
            $this->getSubmissionManager()->setErrorMessage(
                $submission, vsprintf('Error occurred: %s', [$e->getMessage()])
            );
            LiveNotificationController::pushNotification(
                $configurationProfile->getProjectId(),
                LiveNotificationController::getContentId($submission),
                LiveNotificationController::SEVERITY_ERROR,
                vsprintf('<p>Failed processing %s %s in blog %s.</p>', [
                    $submission->getContentType(),
                    $submission->getSourceId(),
                    $submission->getSourceBlogId(),
                ])
            );
            $this->getLogger()->error($e->getMessage());
        }
    }

    /**
     * @param string   $contentType
     * @param int      $sourceBlog
     * @param int      $sourceEntity
     * @param int      $targetBlog
     * @param int|null $targetEntity
     * @param bool     $clone
     * @param string   $batchUid
     *
     * @return bool
     */
    public function createForTranslation($contentType, $sourceBlog, $sourceEntity, $targetBlog, $targetEntity = null, $clone = false, $batchUid = '')
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
        $submission->setBatchUid($batchUid);

        return $this->getSubmissionManager()->storeEntity($submission);
    }
}
