<?php

namespace Smartling\Base;

use Exception;
use JetBrains\PhpStorm\ArrayShape;
use Smartling\ContentTypes\ContentTypeNavigationMenuItem;
use Smartling\DbAl\WordpressContentEntities\Entity;
use Smartling\DbAl\WordpressContentEntities\EntityWithPostStatus;
use Smartling\DbAl\WordpressContentEntities\EntityAbstract;
use Smartling\DbAl\WordpressContentEntities\TaxonomyEntityStd;
use Smartling\Exception\BlogNotFoundException;
use Smartling\Exception\EntityNotFoundException;
use Smartling\Exception\InvalidXMLException;
use Smartling\Exception\NothingFoundForTranslationException;
use Smartling\Exception\SmartlingDbException;
use Smartling\Exception\SmartlingFileDownloadException;
use Smartling\Exception\SmartlingTargetPlaceholderCreationFailedException;
use Smartling\Exception\SmartlingTestRunCheckFailedException;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\DateTimeHelper;
use Smartling\Helpers\EventParameters\AfterDeserializeContentEventParameters;
use Smartling\Helpers\EventParameters\BeforeSerializeContentEventParameters;
use Smartling\Helpers\PostContentHelper;
use Smartling\Helpers\TestRunHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Helpers\XmlHelper;
use Smartling\Jobs\JobEntityWithBatchUid;
use Smartling\Models\UploadQueueItem;
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
        return [
            'entity' => $this->getContentHelper()->readSourceContent($submission)->toArray(),
            'meta' => $this->getContentHelper()->readSourceMetadata($submission),
        ];
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
            $submission->setFileUri($this->fileUriHelper->generateFileUri($submission));
            $submission = $this->getSubmissionManager()->storeEntity($submission);
        }

        return $submission;
    }

    private function createTargetContent(SubmissionEntity $submission): SubmissionEntity
    {
        $submission = $this->getFunctionProxyHelper()->apply_filters(ExportedAPI::FILTER_SMARTLING_PREPARE_TARGET_CONTENT, $submission);
        if (!($submission instanceof SubmissionEntity)) {
            $this->getLogger()->critical('Submission not instance of ' . SubmissionEntity::class . ' after filter ' . ExportedAPI::FILTER_SMARTLING_PREPARE_TARGET_CONTENT) . '. This is most likely due to an error outside of the plugins code.';
        }

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
     * @throws EntityNotFoundException
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

            $originalContent = $this->readSourceContentWithMetadataAsArray($submission);
            $source = $this->externalContentManager->getExternalContent($originalContent, $submission, false);

            $contentEntity = $this->getContentHelper()->readSourceContent($submission);
            $params = new BeforeSerializeContentEventParameters($source, $submission, $contentEntity, $source['meta']);
            do_action(ExportedAPI::EVENT_SMARTLING_BEFORE_SERIALIZE_CONTENT, $params);
            $source = $params->getPreparedFields();
            $this->prepareFieldProcessorValues($submission);
            $filteredValues = $this->getFieldsFilter()->processStringsBeforeEncoding($submission, $source);

            if (0 === count($filteredValues) && !$submission->isCloned()) {
                $message = vsprintf(
                    'Prepared Submission = \'%s\' has nothing to translate. Setting status to \'%s\'.',
                    [
                        $submission->getId(),
                        SubmissionEntity::SUBMISSION_STATUS_FAILED,
                    ]
                );
                $this->getLogger()->warning($message);
                $submission = $this->getSubmissionManager()
                    ->setErrorMessage($submission, 'There is no original content for translation.');

                throw new NothingFoundForTranslationException($message);
            }

            $this->prepareFieldProcessorValues($submission);
            return $this->xmlHelper->xmlEncode($filteredValues, $submission, array_merge($source, $originalContent));
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

    #[ArrayShape(['entity' => 'string[]', 'meta' => 'string[]'])]
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
                    $lockedData['meta'][$_fieldName] = $this->wpProxy->maybe_unserialize($targetMeta[$_fieldName]);
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
     * @return string[] exception messages
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
            $translation = $this->getFunctionProxyHelper()->apply_filters(ExportedAPI::FILTER_BEFORE_TRANSLATION_APPLIED, $translation, $lockedData, $submission);
            if (!is_array($translation)) {
                $this->getLogger()->critical('Translation is not array after applying filter ' . ExportedAPI::FILTER_BEFORE_TRANSLATION_APPLIED . '. This is most likely due to an error outside of the plugins code.');
            }
            $translation = $this->externalContentManager->setExternalContent($decoded->getOriginalFields(), $translation, $submission);
            if ($targetContent instanceof EntityAbstract) {
                $this->setValues($targetContent, $translation['entity'] ?? []);
            } else {
                $targetContent = $targetContent->fromArray($translation['entity']);
            }
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
                $submission->setAppliedDate(DateTimeHelper::nowAsString());
            }
            $this->getContentHelper()->writeTargetContent($submission, $targetContent);
            $this->setObjectTerms($submission);
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
            if (TestRunHelper::isTestRunBlog($submission->getTargetBlogId())) {
                $this->testRunHelper->checkDownloadedSubmission($submission);
            }
            $submission = $this->getSubmissionManager()->storeEntity($submission);
            $this->setPostStatus($configurationProfile, $targetContent, $submission);
            do_action(ExportedAPI::ACTION_AFTER_TRANSLATION_APPLIED, $submission);
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
            $this->handleBadBlogId($submission);
        } catch (SmartlingFileDownloadException $e) {
            /**
             * Even if there is no XML file we may need rebuild target metadata.
             * May happen for attachments and menu items
             */
            $customTypes = [ContentTypeNavigationMenuItem::WP_CONTENT_TYPE, 'attachment'];
            if (0 < $submission->getTargetId() && in_array($submission->getContentType(), $customTypes, true)) {
                $contentHelper = $this->getContentHelper();
                $currentSiteId = $contentHelper->getSiteHelper()->getCurrentSiteId();
                $sourceMetadata = $contentHelper->readSourceMetadata($submission);

                $filteredMetadata = [];

                foreach ($sourceMetadata as $key => $value) {
                    try {
                        // Value is of `mixed` type
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
        } catch (SmartlingTestRunCheckFailedException $e) {
            $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_FAILED);
            $submission->setLastError($e->getMessage());
            $this->getSubmissionManager()->storeEntity($submission);
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
     * @throws SmartlingDbException
     */
    public function bulkSubmit(UploadQueueItem $item): void
    {
        if (count($item->getSubmissions()) === 0) {
            return;
        }
        $submission = $item->getSubmissions()[0];
        $locales = $item->getSmartlingLocales()->getList();
        $profile = $this->getSettingsManager()->getSingleSettingsProfile($submission->getSourceBlogId());
        try {
            $xml = $this->getXMLFiltered($submission);
            if ($xml === '') {
                $this->getLogger()->debug('Skip upload of empty xml');
                return;
            }
            foreach ($item->getSubmissions() as $submission) {
                $submission = $this->prepareUpload($submission);
                $fileUri = $this->fileUriHelper->generateFileUri($submission);
                $submission->setFileUri($fileUri);
                $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS);
                $submission->setSubmissionDate(DateTimeHelper::nowAsString());
                $this->getSubmissionManager()->storeSubmissions([$submission]);
            }

            LiveNotificationController::pushNotification(
                $profile->getProjectId(),
                LiveNotificationController::getContentId($submission),
                LiveNotificationController::SEVERITY_SUCCESS,
                vsprintf('<p>Sending file %s for locales %s.</p>', [
                    $submission->getFileUri(),
                    implode(',', array_values($locales)),
                ])
            );

            $this->getLogger()->info(sprintf(
                'Starting fileUri="%s" upload for contentType="%s", sourceBlogId="%s", sourceId="%s", locales="%s"',
                $submission->getFileUri(),
                $submission->getContentType(),
                $submission->getSourceBlogId(),
                $submission->getSourceId(),
                implode(',', $locales),
            ));

            if ($this->getApiWrapper()->uploadContent($submission, $xml, $item->getBatchUid(), $locales)) {
                LiveNotificationController::pushNotification(
                    $profile->getProjectId(),
                    LiveNotificationController::getContentId($submission),
                    LiveNotificationController::SEVERITY_SUCCESS,
                    vsprintf('<p>Sent file %s for locales %s.</p>', [
                        $submission->getFileUri(),
                        implode(',', $locales),
                    ])
                );
            } else {
                LiveNotificationController::pushNotification(
                    $profile->getProjectId(),
                    LiveNotificationController::getContentId($submission),
                    LiveNotificationController::SEVERITY_ERROR,
                    vsprintf('<p>Failed sending file %s for locales %s.</p>', [
                        $submission->getFileUri(),
                        implode(',', $locales),
                    ])
                );
            }
        } catch (\Exception $e) {
            $caught = $e;
            do {
                if (401 === $e->getCode()) {
                    $this->getLogger()->error('Invalid credentials. Check profile settings.');
                    break;
                }
                $e = $e->getPrevious();
            } while ($e !== null);
            $e = $caught;
            $this->getLogger()->error($e->getMessage());
            foreach ($item->getSubmissions() as $submission) {
                $this->getSubmissionManager()
                    ->setErrorMessage($submission, vsprintf('Could not submit because: %s', [$e->getMessage()]));
            }
            $submission = $item->getSubmissions()[0];

            LiveNotificationController::pushNotification(
                $profile->getProjectId(),
                LiveNotificationController::getContentId($submission),
                LiveNotificationController::SEVERITY_ERROR,
                vsprintf('<p>Failed sending file %s.</p>', [
                    $submission->getFileUri(),
                ])
            );
        }
    }

    public function sendForTranslation(UploadQueueItem $item): void
    {
        foreach ($item->getSubmissions() as $submission) {
            if (1 === $submission->getIsLocked()) {
                $this->getLogger()
                    ->notice(sprintf('Requested upload of locked submissionId=%s, skipping.', $submission->getId()));

                $item = $item->removeSubmission($submission);
            }
            if ($submission->isCloned()) {
                $this->getLogger()
                    ->notice(sprintf('Requested upload of cloned submissionId=%s, skipping.', $submission->getId()));

                $item = $item->removeSubmission($submission);
            }
            $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_NEW);
        }
        if (count($item->getSubmissions()) === 0) {
            $this->getLogger()->debug('No items to send after removing locked submissions');
            return;
        }

        $configurationProfile = $this->getSettingsManager()->getSingleSettingsProfile($item->getSubmissions()[0]->getSourceBlogId());

        // Mark attachment submission as "Cloned" if there is "Clone attachment"
        // option is enabled in configuration profile.
        foreach ($item->getSubmissions() as $submission) {
            if (1 === $configurationProfile->getCloneAttachment() && $submission->getContentType() === 'attachment') {
                $submission->setIsCloned(1);
                $this->getSubmissionManager()->storeEntity($submission);

                $this->getLogger()->info(
                    sprintf(
                        'Attachment submissionId="%s" marked as cloned (sourceBlogId="%s", sourceId="%s", contentType="%s", batchUid="%s").',
                        $submission->getId(),
                        $submission->getSourceBlogId(),
                        $submission->getSourceId(),
                        $submission->getContentType(),
                        $item->getBatchUid(),
                    )
                );
                $item = $item->removeSubmission($submission);
            }
        }
        if (count($item->getSubmissions()) === 0) {
            $this->getLogger()->debug('No items to send after removing attachments for cloning');
            return;
        }

        $submission = $item->getSubmissions()[0];
        $this->getLogger()->debug(
            sprintf(
                'Preparing to send submissionIds="%s" (sourceBlogId="%s", sourceId="%s", contentType="%s", batchUid="%s").',
                    implode(', ', array_map(static function (SubmissionEntity $submission) {
                        return $submission->getId();
                    }, $item->getSubmissions())),
                    $submission->getSourceBlogId(),
                    $submission->getSourceId(),
                    $submission->getContentType(),
                    $item->getBatchUid(),
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
            $this->getLogger()->withStringContext([
                'sourceBlogId' => $submission->getSourceBlogId(),
                'sourceId' => $submission->getSourceId(),
                'submissionId' => $submission->getId(),
                'targetBlogId' => $submission->getTargetBlogId(),
                'targetId' => $submission->getTargetId(),
            ], function () use ($item) {
                $this->bulkSubmit($item);
            });
        } catch (Exception $e) {
            $this->getSubmissionManager()->setErrorMessage(
                $submission, vsprintf('Error occurred: %s', [$e->getMessage()])
            );
            LiveNotificationController::pushNotification(
                $configurationProfile->getProjectId(),
                LiveNotificationController::getContentId($submission),
                LiveNotificationController::SEVERITY_ERROR,
                vsprintf('<p>Failed processing %s id %s in blog %s.</p>', [
                    $submission->getContentType(),
                    $submission->getSourceId(),
                    $submission->getSourceBlogId(),
                ])
            );
            $this->getLogger()->error($e->getMessage());
        }
    }

    public function prepareForUpload(string $contentType, int $sourceBlog, int $sourceEntity, int $targetBlog, JobEntityWithBatchUid $jobInfo, bool $clone): SubmissionEntity
    {
        $translationHelper = $this->getTranslationHelper();
        $submission = $translationHelper
            ->prepareSubmissionEntity($contentType, $sourceBlog, $sourceEntity, $targetBlog);

        $contentEntity = $this->getContentHelper()->readSourceContent($submission);

        if (null === $submission->getId()) {
            $submission->setSourceContentHash('');
            $submission->setSourceTitle($contentEntity->getTitle());
            $submission->setFileUri($this->fileUriHelper->generateFileUri($submission));
        } elseif ($submission->isLocked()) {
            $this->getLogger()
                ->debug(sprintf('Requested upload of locked submissionId=%s. Skipping.', $submission->getId()));
        } else {
            $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_NEW);
        }

        $isCloned = true === $clone ? 1 : 0;
        $submission->setIsCloned($isCloned);
        $submission->setJobInfo($jobInfo->getJobInformationEntity());

        return $this->getSubmissionManager()->storeEntity($submission);
    }

    private function removeExcludedFields(array $fields, ConfigurationProfileEntity $configurationProfile): array
    {
        return $this->getFieldsFilter()->removeFields($fields, $configurationProfile->getFilterSkipArray(), $configurationProfile->getFilterFieldNameRegExp());
    }

    private function processPostContentBlocks(Entity $targetContent, array $original, array $translation, SubmissionEntity $submission, PostContentHelper $postContentHelper, array $lockedEntityFields): array
    {
        if (array_key_exists('entity', $translation) && ArrayHelper::notEmpty($translation['entity'])) {
            $targetContentArray = $targetContent->toArray();
            if (array_key_exists('post_content', $translation['entity']) && array_key_exists('post_content', $targetContentArray)) {
                $translation['entity']['post_content'] = $postContentHelper->applyContentWithBlockLocks(
                    $targetContentArray['post_content'],
                    $postContentHelper->replacePostTranslate($original['entity']['post_content'] ?? '', $translation['entity']['post_content']),
                );
            }
            $translation['entity'] = self::arrayMergeIfKeyNotExists($lockedEntityFields, $translation['entity']);
        }

        return $translation;
    }

    private function setObjectTerms(SubmissionEntity $submission): void {
        $wrapper = $this->getContentHelper()->getWrapper($submission->getContentType());
        if ($wrapper instanceof TaxonomyEntityStd) { // Taxonomies have no terms
            return;
        }
        $result = [];
        $terms = $this->wpProxy->getObjectTerms($submission->getSourceId());
        if ($terms instanceof \WP_Error) {
            $this->getLogger()->error("Failed to get object terms submissionId={$submission->getId()}, sourceId={$submission->getSourceId()}: " . $terms->get_error_message());
            return;
        }
        foreach ($terms as $term) {
            $relatedSubmission = $this->getSubmissionManager()->findOne([
                SubmissionEntity::FIELD_CONTENT_TYPE => $term->taxonomy,
                SubmissionEntity::FIELD_SOURCE_BLOG_ID => $submission->getSourceBlogId(),
                SubmissionEntity::FIELD_SOURCE_ID => $term->term_id,
                SubmissionEntity::FIELD_TARGET_BLOG_ID => $submission->getTargetBlogId(),
            ]);
            if ($relatedSubmission !== null) {
                $term->term_id = $relatedSubmission->getTargetId();
                if ($term->parent !== 0) {
                    $parent = $this->getSubmissionManager()->findOne([
                        SubmissionEntity::FIELD_CONTENT_TYPE => $term->taxonomy,
                        SubmissionEntity::FIELD_SOURCE_BLOG_ID => $submission->getSourceBlogId(),
                        SubmissionEntity::FIELD_SOURCE_ID => $term->parent,
                        SubmissionEntity::FIELD_TARGET_BLOG_ID => $submission->getTargetBlogId(),
                    ]);
                    if ($parent !== null) {
                        $term->parent = $parent->getTargetId();
                    }
                }
            }
            $result[$term->taxonomy][] = $term->term_id;
        }
        $this->getContentHelper()->getSiteHelper()->withBlog($submission->getTargetBlogId(), function () use ($result, $submission) {
            foreach ($result as $taxonomy => $ids) {
                $result = $this->wpProxy->setObjectTerms($submission->getTargetId(), $ids, $taxonomy);
                if ($result instanceof \WP_Error) {
                    $this->getLogger()->error("Failed to set object terms submissionId={$submission->getId()}, sourceId={$submission->getSourceId()}: " . $result->get_error_message());
                }
            }
        });
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
                ->processAttributeOnDownload($originalMetadata['_menu_item_object_id'], $originalMetadata['_menu_item_object_id'], $submission); // two originalMetadata here is not a typo, translated id is discarded
        }

        return $result;
    }

    /**
     * @noinspection PhpUnusedPrivateMethodInspection
     * @see SmartlingCoreDownloadTrait::downloadTranslationBySubmission
     */
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

    private function setPostStatus(
        ConfigurationProfileEntity $configurationProfile,
        Entity $targetContent,
        SubmissionEntity $submission,
    ): void
    {
        $translationPublishingMode = $configurationProfile->getTranslationPublishingMode();
        if (ConfigurationProfileEntity::TRANSLATION_PUBLISHING_MODE_NO_CHANGE !== $translationPublishingMode && $targetContent instanceof EntityWithPostStatus) {
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
    }
}
