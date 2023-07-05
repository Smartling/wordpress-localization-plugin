<?php

namespace Smartling\Base;

use Smartling\DbAl\WordpressContentEntities\EntityWithPostStatus;
use Smartling\DbAl\WordpressContentEntities\EntityAbstract;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\ContentSerializationHelper;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

trait SmartlingCoreTrait
{
    use SmartlingCoreUploadTrait;
    use SmartlingCoreDownloadTrait;
    use SmartlingCoreAttachments;

    private function prepareFieldProcessorValues(SubmissionEntity $submission): void
    {
        ContentSerializationHelper::prepareFieldProcessorValues($this->getSettingsManager(), $submission);
    }

    public function prepareTargetContent(SubmissionEntity $submission): SubmissionEntity
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
     */
    protected function prepareTargetEntity(SubmissionEntity $submission): SubmissionEntity
    {
        $update = 0 !== $submission->getTargetId();

        if (true === $update && !$submission->isCloned()) {
            return $submission;
        }

        $this->getLogger()->debug(
            sprintf('Preparing target entity for submissionId=%s, targetBlogId="%s".',
                $submission->getId(),
                $submission->getTargetBlogId(),
            )
        );

        $targetContent = $update ?
            $this->getContentHelper()->readTargetContent($submission) :
            $this->getContentHelper()->readSourceContent($submission)->forInsert();

        $this->prepareFieldProcessorValues($submission);
        $unfilteredSourceData = $this->readSourceContentWithMetadataAsArray($submission);

        $filteredData = $submission->isCloned() ? $unfilteredSourceData : $this->getFieldsFilter()->removeIgnoringFields($submission, $unfilteredSourceData);

        unset ($filteredData['entity']['ID'], $filteredData['entity']['term_id'], $filteredData['entity']['id']);

        if (array_key_exists('entity', $filteredData) &&
            $targetContent instanceof EntityAbstract &&
            ArrayHelper::notEmpty($filteredData['entity'])
        ) {
            foreach ($filteredData['entity'] as $k => $v) {
                $targetContent->{$k} = apply_filters(ExportedAPI::FILTER_SMARTLING_METADATA_FIELD_PROCESS, $k, $v, $submission);
            }
        }

        if ($targetContent instanceof EntityWithPostStatus) {
            $targetContent->translationDrafted();
        }

        $targetContent = $this->getContentHelper()->writeTargetContent($submission, $targetContent);
        $submission->setTargetId($targetContent->getId());
        $submission = $this->getSubmissionManager()->storeEntity($submission);
        $this->externalContentManager->setExternalContent($unfilteredSourceData, $this->externalContentManager->getExternalContent([], $submission, true), $submission);
        $this->setObjectTerms($submission);

        $this->getLogger()
            ->debug(
                vsprintf(
                    'Created target entity for submission = \'%s\' for locale = \'%s\' in blog =\'%s\', id = \'%s\'.',
                    [
                        $submission->getId(),
                        $submission->getTargetLocale(),
                        $submission->getTargetBlogId(),
                        $targetContent->getId(),
                    ]
                )
            );

        if (array_key_exists('meta', $filteredData) && ArrayHelper::notEmpty($filteredData['meta'])) {
            $metaFields = $filteredData['meta'];
            foreach ($metaFields as $metaName => & $metaValue) {
                $metaValue = apply_filters(ExportedAPI::FILTER_SMARTLING_METADATA_FIELD_PROCESS, $metaName, $metaValue, $submission);
            }
            unset ($metaValue);
            $this->getContentHelper()->writeTargetMetadata($submission, $metaFields);
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
