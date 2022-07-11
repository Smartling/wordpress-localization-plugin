<?php

namespace Smartling\Base;

use Smartling\DbAl\WordpressContentEntities\EntityAbstract;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\ContentSerializationHelper;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

/**
 * Class SmartlingCoreTrait
 * @package Smartling\Base
 */
trait SmartlingCoreTrait
{
    use SmartlingCoreUploadTrait;
    use SmartlingCoreDownloadTrait;
    use SmartlingCoreAttachments;

    /**
     * @param SubmissionEntity $submission
     *
     * @throws \Smartling\Exception\SmartlingConfigException
     */
    private function prepareFieldProcessorValues(SubmissionEntity $submission)
    {
        ContentSerializationHelper::prepareFieldProcessorValues($this->getSettingsManager(), $submission);
    }

    /**
     * @param SubmissionEntity $submission
     *
     * @return SubmissionEntity
     */
    public function prepareTargetContent(SubmissionEntity $submission)
    {
        try {
            $resultSubmission = $this->prepareTargetEntity($submission);

            if (0 < count(SubmissionManager::getChangedFields($resultSubmission))) {
                $resultSubmission = $this->getSubmissionManager()->storeEntity($resultSubmission);
            }
        } catch (\Exception $e) {
            $submission->setLastError($e->getMessage());
            $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_FAILED);
            $resultSubmission = $this->getSubmissionManager()->storeEntity($submission);
        }

        return $this->getTranslationHelper()->reloadSubmission($resultSubmission);
    }

    /**
     * Prepares a duplicate of source content for target site and links them.
     * To be used JUST BEFORE SENDING to Smartling
     *
     * @param SubmissionEntity $submission
     * @param bool             $forceClone
     *
     * @return SubmissionEntity
     * @throws \Smartling\Exception\SmartlingConfigException
     * @throws \Smartling\Exception\SmartlingWpDataIntegrityException
     */
    protected function prepareTargetEntity(SubmissionEntity $submission, $forceClone = false)
    {

        $update = 0 !== $submission->getTargetId();


        if (true === $update && false === $forceClone) {
            return $submission;
        }

        $this->getLogger()->debug(
            vsprintf(
                'Cloning target entity for submission = \'%s\' for locale = \'%s\' in blog =\'%s\'.',
                [
                    $submission->getId(),
                    $submission->getTargetLocale(),
                    $submission->getTargetBlogId(),
                ]
            )
        );

        $originalContent = $this->getContentHelper()->readSourceContent($submission);

        if (false === $update) {
            /** @var EntityAbstract $originalContent */
            $targetContent = clone $originalContent;
            $targetContent->cleanFields();
        } else {
            $targetContent = $this->getContentHelper()->readTargetContent($submission);
        }


        $this->prepareFieldProcessorValues($submission);
        $unfilteredSourceData = $this->readSourceContentWithMetadataAsArray($submission);

        // filter as on download but clone;

        $hardFilteredOriginalData = $this->getFieldsFilter()->removeIgnoringFields($submission, $unfilteredSourceData);

        unset ($hardFilteredOriginalData['entity']['ID'], $hardFilteredOriginalData['entity']['term_id'], $hardFilteredOriginalData['entity']['id']);

        if (array_key_exists('entity', $hardFilteredOriginalData) &&
            ArrayHelper::notEmpty($hardFilteredOriginalData['entity'])
        ) {
            $_entity = &$hardFilteredOriginalData['entity'];
            /**
             * @var array $_entity
             */
            foreach ($_entity as $k => $v) {
                // Value is of `mixed` type
                $targetContent->{$k} = apply_filters(ExportedAPI::FILTER_SMARTLING_METADATA_FIELD_PROCESS, $k, $v, $submission);
            }
        }

        if (false === $forceClone) {
            $targetContent->translationDrafted();
        }

        $targetContent = $this->getContentHelper()->writeTargetContent($submission, $targetContent);
        $submission->setTargetId($targetContent->getPK());
        $submission = $this->getSubmissionManager()->storeEntity($submission);
        $this->externalContentManager->setExternalContent($this->externalContentManager->getExternalContent([], $submission, true), $submission);

        $this->getLogger()
            ->debug(
                vsprintf(
                    'Created target entity for submission = \'%s\' for locale = \'%s\' in blog =\'%s\', id = \'%s\'.',
                    [
                        $submission->getId(),
                        $submission->getTargetLocale(),
                        $submission->getTargetBlogId(),
                        $targetContent->getPK(),
                    ]
                )
            );

        if (array_key_exists('meta', $hardFilteredOriginalData) &&
            ArrayHelper::notEmpty($hardFilteredOriginalData['meta'])
        ) {
            $metaFields = &$hardFilteredOriginalData['meta'];
            /**
             * @var array $metaFields
             */
            foreach ($metaFields as $metaName => & $metaValue) {
                /* @var mixed $metaValue */
                $metaValue = apply_filters(ExportedAPI::FILTER_SMARTLING_METADATA_FIELD_PROCESS, $metaName, $metaValue, $submission);
            }
            unset ($metaValue);
            $this->getContentHelper()->writeTargetMetadata($submission, $metaFields);
        }

        if (false === $update) {
            $submission->setTargetId($targetContent->getPK());
            $submission = $this->getSubmissionManager()->storeEntity($submission);
        }

        try {
            $this->getMultilangProxy()->linkObjects($submission);
        } catch (\Error $e) {
            $this->getLogger()->notice("Caught exception while trying to link objects for submission {$submission->getId()}. " .
                "Error was: {$e->getMessage()}");
        }

        if ('attachment' === $submission->getContentType()) {
            do_action(ExportedAPI::ACTION_SMARTLING_SYNC_MEDIA_ATTACHMENT, $submission);
        }

        return $submission;
    }
}
