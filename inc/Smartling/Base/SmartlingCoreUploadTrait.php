<?php

namespace Smartling\Base;

use Exception;
use JetBrains\PhpStorm\ArrayShape;
use Smartling\ApiWrapperInterface;
use Smartling\ContentTypes\ContentTypeNavigationMenuItem;
use Smartling\DbAl\WordpressContentEntities\EntityAbstract;
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
use Smartling\Helpers\PostContentHelper;
use Smartling\Helpers\SiteHelper;
use Smartling\Helpers\StringHelper;
use Smartling\Helpers\TranslationHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Helpers\XmlHelper;
use Smartling\Jobs\JobEntityWithBatchUid;
use Smartling\Replacers\ContentIdReplacer;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Submissions\SubmissionEntity;
use Smartling\WP\Controller\LiveNotificationController;

trait SmartlingCoreUploadTrait
{
    private function renewContentHash(SubmissionEntity $submission): SubmissionEntity
    {
        $content = $this->getContentHelper()->readSourceContent($submission);
        $newHash = $this->getContentSerializationHelper()->calculateHash($submission);
        $submission->setSourceContentHash($newHash);
        $submission->setOutdated(0);
        $submission->setSourceTitle($content->getTitle());

        return $this->getSubmissionManager()->storeEntity($submission);
    }

    #[ArrayShape(['entity' => [], 'meta' => []])]
    private function readSourceContentWithMetadataAsArray(SubmissionEntity $submission): array
    {
        $source = [
            'entity' => $this->getContentHelper()->readSourceContent($submission)->toArray(),
            'meta'   => $this->getContentHelper()->readSourceMetadata($submission),
        ];

        if (!is_array($source['meta'])) {
            $source['meta'] = [];
        }

        return $source;
    }

    protected function getFunctionProxyHelper(): WordpressFunctionProxyHelper
    {
        return new WordpressFunctionProxyHelper();
    }

    public function prepareUpload(SubmissionEntity $submission): SubmissionEntity
    {
        return $this->renewContentHash(
            $this->createTargetContent(
                $this->setFileUriIfNullId($submission)
            )
        );
    }

    private function setFileUriIfNullId(SubmissionEntity $submission): SubmissionEntity
    {
        if (null === $submission->getId()) {
            // generate URI
            $submission->getFileUri();
            $submission = $this->getSubmissionManager()->storeEntity($submission);
        }

        return $submission;
    }

    private function createTargetContent(SubmissionEntity $submission): SubmissionEntity
    {
        $submission = $this->getFunctionProxyHelper()->apply_filters(ExportedAPI::FILTER_SMARTLING_PREPARE_TARGET_CONTENT, $submission);

        if (SubmissionEntity::SUBMISSION_STATUS_FAILED === $submission->getStatus()) {
            $msg = vsprintf(
                'Failed creating target placeholder for submission id=\'%s\', source_blog_id=\'%s\', source_id=\'%s\', target_blog_id=\'%s\', target_id=\'%s\' with message: \'%s\'',
                [
                    $submission->getId(),
                    $submission->getSourceBlogId(),
                    $submission->getSourceId(),
                    $submission->getTargetBlogId(),
                    $submission->getTargetId(),
                    $submission->getLastError(),
                ]
            );
            $this->getLogger()->error($msg);
            throw new SmartlingTargetPlaceholderCreationFailedException($msg);
        }

        return $submission;
    }

    /**
     * Prepare submission for upload and return XML string for translation
     * @see prepareUpload
     */
    public function getXMLFiltered(SubmissionEntity $submission): string
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
            $submission = $this->prepareUpload($submission);

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
            }

            $this->prepareFieldProcessorValues($submission);
            return $this->xmlHelper->xmlEncode($filteredValues, $submission, $source);
        } catch (EntityNotFoundException $e) {
            $this->getLogger()->error($e->getMessage());
            $this->getSubmissionManager()->setErrorMessage($submission, 'Submission references non existent content.');
        } catch (BlogNotFoundException $e) {
            $this->getSubmissionManager()->setErrorMessage($submission, 'Submission references non existent blog.');
            $this->handleBadBlogId($submission);
        } catch (NothingFoundForTranslationException $e) {
        } catch (Exception $e) {
            $this->getSubmissionManager()
                ->setErrorMessage($submission, vsprintf('Error occurred: %s', [$e->getMessage()]));
            $this->getLogger()->error($e->getMessage());
            throw $e;
        }
        return '';
    }

    #[ArrayShape(['entity' => 'string', 'meta' => 'string'])]
    private function readLockedTranslationFieldsBySubmission(SubmissionEntity $submission): array
    {
        $this->getLogger()
            ->debug(vsprintf('Starting loading locked fields for submission id=%s', [$submission->getId()]));

        $lockedData = [
            'entity' => [],
            'meta'   => [],
        ];

        if (0 === $submission->getTargetId()) {
            $this->getLogger()->debug("There is still no translation or placeholder for submission id={$submission->getId()}");
            return $lockedData;
        }

        $targetContent = $this->getContentHelper()->readTargetContent($submission)->toArray();
        $targetMeta = $this->getContentHelper()->readTargetMetadata($submission);

        $this->getLogger()->debug(vsprintf('Got target metadata: %s.', [var_export($targetMeta, true)]));

        foreach ($submission->getLockedFields() as $lockedFieldName) {
            if (preg_match('/^meta\//iu', $lockedFieldName)) {
                $_fieldName = preg_replace('/^meta\//iu', '', $lockedFieldName);
                $this->getLogger()->debug(vsprintf('Got field \'%s\'', [$_fieldName]));
                if (array_key_exists($_fieldName, $targetMeta)) {
                    $lockedData['meta'][$_fieldName] = $targetMeta[$_fieldName];
                }
            } elseif (preg_match('/^entity\//iu', $lockedFieldName)) {
                $_fieldName = preg_replace('/^entity\//iu', '', $lockedFieldName);
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
     * @return string[]
     */
    public function applyXML(SubmissionEntity $submission, string $xml, XmlHelper $xmlHelper, PostContentHelper $postContentHelper): array
    {
        $messages = [];

        try {
            $lockedData = $this->readLockedTranslationFieldsBySubmission($submission);

            $this->prepareFieldProcessorValues($submission);

            $decoded = $xmlHelper->xmlDecode($xml, $submission);
            $translation = $decoded->getTranslatedFields();

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
            $translation = $this->processPostContentBlocks($targetContent, $original, $translation, $submission, $postContentHelper, $lockedData['entity']);
            $this->setValues($targetContent, $translation);
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
                $translationPublishingMode = $configurationProfile->getTranslationPublishingMode();
                if (ConfigurationProfileEntity::TRANSLATION_PUBLISHING_MODE_NO_CHANGE !== $translationPublishingMode) {
                    $this->getLogger()->debug(
                        vsprintf(
                            'Submission id=%s (blog=%s, item=%s, content-type=%s) setting status %s for translation',
                            [
                                $submission->getId(),
                                $submission->getSourceBlogId(),
                                $submission->getSourceId(),
                                $submission->getContentType(),
                                $translationPublishingMode === ConfigurationProfileEntity::TRANSLATION_PUBLISHING_MODE_PUBLISH ? 'publish' : 'draft',
                            ]
                        )
                    );
                    switch ($translationPublishingMode) {
                        case ConfigurationProfileEntity::TRANSLATION_PUBLISHING_MODE_PUBLISH:
                            $targetContent->translationCompleted();
                            break;
                        case ConfigurationProfileEntity::TRANSLATION_PUBLISHING_MODE_DRAFT:
                            $targetContent->translationDrafted();
                            break;
                        default:
                            throw new \RuntimeException("Unexpected value $translationPublishingMode in profile setting \"translation publishing mode\"");
                    }
                }
                $submission->setAppliedDate(DateTimeHelper::nowAsString());
            }
            $this->getContentHelper()->writeTargetContent($submission, $targetContent);
            if (array_key_exists('meta', $translation) && ArrayHelper::notEmpty($translation['meta'])) {
                $metaFields = &$translation['meta'];

                if (1 === $configurationProfile->getCleanMetadataOnDownload()) {
                    $this->getContentHelper()->removeTargetMetadata($submission);
                    $xmlFields = $decoded->getOriginalFields();
                    if (array_key_exists('meta', $xmlFields)) {
                        $metaFields = array_merge($this->removeExcludedFields($xmlFields['meta'], $configurationProfile), $metaFields);
                    }
                }
                $metaFields = self::arrayMergeIfKeyNotExists($lockedData['meta'], $metaFields);
                if (array_key_exists('meta', $original)) {
                    $metaFields = $this->fixMetadata($submission, $original['meta'], $metaFields);
                }

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
                        $this->getLogger()->debug(
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
                    $diff = array_map(static function($value, $index) use ($filteredMetadata) {
                        return [
                            'old_value' => $value,
                            'new_value' => $filteredMetadata[$index],
                        ];
                    }, $diff, array_keys($diff));
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

    public function bulkSubmit(SubmissionEntity $submission): void
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

            $this->executeBatchIfNoSubmissionsPending($submission->getBatchUid(), $submission->getSourceBlogId());
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

    private function executeBatchIfNoSubmissionsPending(string $batchUid, int $sourceBlogId): void
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
            }
        } catch (Exception $e) {
            $msg = vsprintf('Error executing batch "%s". Message: "%s"', [$batchUid, $e->getMessage()]);
            $this->getLogger()->error($msg);
        }
    }

    private function fixSubmissionBatchUid(SubmissionEntity $submission): SubmissionEntity
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

            /** @var ApiWrapperInterface $apiWrapper */
            $apiWrapper = $this->getApiWrapper();
            $jobInfo = $apiWrapper->retrieveJobInfoForDailyBucketJob($profile, $profile->getAutoAuthorize());

            $submission->setBatchUid($jobInfo->getBatchUid());
            $submission->setJobInfo($jobInfo->getJobInformationEntity());
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

    public function sendForTranslationBySubmission(SubmissionEntity $submission): void
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
        if (1 === $configurationProfile->getCloneAttachment() && $submission->getContentType() === 'attachment') {
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
                $this->applyXML($submission, $xml, $this->xmlHelper, $this->postContentHelper);

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

    public function createForTranslation(string $contentType, int $sourceBlog, int $sourceEntity, int $targetBlog, JobEntityWithBatchUid $jobInfo, bool $clone): SubmissionEntity
    {
        /**
         * @var TranslationHelper $translationHelper
         */
        $translationHelper = $this->getTranslationHelper();
        $submission = $translationHelper
            ->prepareSubmissionEntity($contentType, $sourceBlog, $sourceEntity, $targetBlog);

        $contentEntity = $this->getContentHelper()->readSourceContent($submission);

        if (null === $submission->getId()) {
            $submission->setSourceContentHash('');
            $submission->setSourceTitle($contentEntity->getTitle());

            // generate URI
            $submission->getFileUri();
        } elseif (0 === $submission->getIsLocked()) {
            $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_NEW);
        } else {
            $this->getLogger()
                ->debug(vsprintf('Requested re-upload of protected submission id=%s. Skipping.', [$submission->getId()]));
        }

        $isCloned = true === $clone ? 1 : 0;
        $submission->setIsCloned($isCloned);
        $submission->setBatchUid($jobInfo->getBatchUid());
        $submission->setJobInfo($jobInfo->getJobInformationEntity());

        return $this->getSubmissionManager()->storeEntity($submission);
    }

    private function removeExcludedFields(array $fields, ConfigurationProfileEntity $configurationProfile): array
    {
        return $this->getFieldsFilter()->removeFields($fields, $configurationProfile->getFilterSkipArray(), $configurationProfile->getFilterFieldNameRegExp());
    }

    private function processPostContentBlocks(EntityAbstract $targetContent, array $original, array $translation, SubmissionEntity $submission, PostContentHelper $postContentHelper, array $lockedData): array
    {
        if (array_key_exists('entity', $translation) && ArrayHelper::notEmpty($translation['entity'])) {
            $targetContentArray = $targetContent->toArray();
            if (array_key_exists('post_content', $translation['entity']) && array_key_exists('post_content', $targetContentArray)) {
                $translation['entity']['post_content'] = $this->applyBlockLevelLocks(
                    $targetContentArray,
                    $postContentHelper->replacePostTranslate($original['entity']['post_content'] ?? '', $translation['entity']['post_content']),
                    $submission,
                    $postContentHelper
                );
            }
            return self::arrayMergeIfKeyNotExists($lockedData, $translation['entity']);
        }

        return $translation;
    }

    private function applyBlockLevelLocks(array $targetContent, string $translatedContent, SubmissionEntity $submission, PostContentHelper $postContentHelper): string
    {
        $lockedBlocks = $postContentHelper->getLockedBlockPathsFromContentString($targetContent['post_content']);
        if (count($lockedBlocks) > 0) {
            return $postContentHelper->applyTranslationsWithLockedBlocks($targetContent['post_content'], $translatedContent, $lockedBlocks);
        }

        if (count($submission->getLockedFields()) > 0) { // TODO remove after deprecation period
            return $postContentHelper->applyBlockLevelLocks($targetContent['post_content'], $translatedContent, $submission->getLockedFields());
        }
        return $translatedContent;
    }

    /**
     * Modifies $entity
     */
    private function setValues(EntityAbstract $entity, array $properties): void
    {
        foreach ($properties as $propertyName => $propertyValue) {
            if ($entity->{$propertyName} !== $propertyValue) {
                $message = vsprintf(
                    'Replacing field %s with value %s to value %s',
                    [
                        $propertyName,
                        json_encode($entity->{$propertyName}, JSON_UNESCAPED_UNICODE),
                        json_encode($propertyValue, JSON_UNESCAPED_UNICODE),
                    ]
                );
                $this->getLogger()->debug($message);
                $entity->{$propertyName} = $propertyValue;
            }
        }
    }

    private function fixMetadata(SubmissionEntity $submission, array $originalMetadata, array $translatedMetadata): array
    {
        $result = $translatedMetadata;
        if (array_key_exists('_menu_item_type', $originalMetadata) &&
            array_key_exists('_menu_item_object', $originalMetadata) &&
            array_key_exists('_menu_item_object_id', $originalMetadata) &&
            $submission->getTargetId() !== 0 &&
            $submission->getContentType() === ContentTypeNavigationMenuItem::WP_CONTENT_TYPE &&
            in_array($originalMetadata['_menu_item_type'], ['taxonomy', 'post_type'], true)
        ) {
            $result['_menu_item_object_id'] = (new ContentIdReplacer($this->getSubmissionManager()))
                ->processOnDownload($originalMetadata['_menu_item_object_id'], $originalMetadata['_menu_item_object_id'], $submission); // two originalMetadata here is not a typo, translated id is discarded
        }

        return $result;
    }

    private function getXml(SubmissionEntity $submission): string
    {
        $source = $this->readSourceContentWithMetadataAsArray($submission);

        $params = new BeforeSerializeContentEventParameters(
            $source,
            $submission,
            $this->getContentHelper()->readSourceContent($submission),
            $source['meta'],
        );

        do_action(ExportedAPI::EVENT_SMARTLING_BEFORE_SERIALIZE_CONTENT, $params);

        $this->prepareFieldProcessorValues($submission);
        $filteredValues = $this->getFieldsFilter()->processStringsBeforeEncoding($submission, $params->getPreparedFields());

        if (0 === count($filteredValues)) {
            $this->getLogger()->debug("Submission id=\"{$submission->getId()}\" has nothing to translate.");
            return '';
        }

        return $this->xmlHelper->xmlEncode($filteredValues, $submission, $params->getPreparedFields());
    }
}
