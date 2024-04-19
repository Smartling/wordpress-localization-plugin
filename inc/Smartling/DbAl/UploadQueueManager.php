<?php

namespace Smartling\DbAl;

use Smartling\ApiWrapperInterface;
use Smartling\Exception\SmartlingDbException;
use Smartling\Helpers\DateTimeHelper;
use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Helpers\QueryBuilder\Condition\Condition;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBlock;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBuilder;
use Smartling\Helpers\QueryBuilder\QueryBuilder;
use Smartling\Models\IntegerIterator;
use Smartling\Models\IntStringPair;
use Smartling\Models\IntStringPairCollection;
use Smartling\Models\UploadQueueEntity;
use Smartling\Models\UploadQueueItem;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;
use Smartling\Vendor\Smartling\Exceptions\SmartlingApiException;

class UploadQueueManager {
    use LoggerSafeTrait;
    private string $tableName;
    public function __construct(
        private ApiWrapperInterface $api,
        private SettingsManager $settingsManager,
        private SmartlingToCMSDatabaseAccessWrapperInterface $db,
        private SubmissionManager $submissionManager,
    ) {
        $this->tableName = $db->completeTableName(UploadQueueEntity::TABLE_NAME);
    }

    public function count(): int
    {
        return (int)$this->db->getRowArray("SELECT COUNT(*) cnt from $this->tableName")['cnt'];
    }

    public function dequeue(): ?UploadQueueItem
    {
        while (($result = $this->db->getRowArray(QueryBuilder::buildSelectQuery($this->tableName, [
            UploadQueueEntity::FIELD_ID,
            UploadQueueEntity::FIELD_BATCH_UID,
            UploadQueueEntity::FIELD_SUBMISSION_IDS,
        ]))) !== null) {
            $this->delete($result[UploadQueueEntity::FIELD_ID]);
            $batchUid = $result[UploadQueueEntity::FIELD_BATCH_UID];
            $locales = new IntStringPairCollection();
            $submissions = [];
            foreach(IntegerIterator::fromString($result[UploadQueueEntity::FIELD_SUBMISSION_IDS]) as $submissionId) {
                $submission = $this->submissionManager->getEntityById($submissionId);
                if ($submission === null) {
                    continue 2;
                }

                $locale = $this->getSmartlingLocale($submission);
                if ($locale === null) {
                    continue 2;
                }

                $locales = $locales->add([new IntStringPair($submission->getId(), $locale)]);
                $submissions[] = $submission;
            }

            return new UploadQueueItem($submissions, $batchUid, $locales);
        }

        return null;
    }

    public function enqueue(IntegerIterator $submissionIds, string $batchUid): void
    {
        if (count($submissionIds) > 0) {
            $this->db->query(QueryBuilder::buildInsertQuery($this->tableName, [
                UploadQueueEntity::FIELD_BATCH_UID => $batchUid,
                UploadQueueEntity::FIELD_CREATED => DateTimeHelper::nowAsString(),
                UploadQueueEntity::FIELD_SUBMISSION_IDS => $submissionIds->serialize(),
            ]));
        }
    }

    public function purge(): void
    {
        $items = $this->db->getResultsArray(QueryBuilder::buildSelectQuery($this->tableName, [
            UploadQueueEntity::FIELD_ID,
            UploadQueueEntity::FIELD_BATCH_UID,
            UploadQueueEntity::FIELD_SUBMISSION_IDS,
        ]));
        $this->db->query("TRUNCATE $this->tableName");
        $profiles = [];
        foreach ($items as $item) {
            foreach (IntegerIterator::fromString($item[UploadQueueEntity::FIELD_SUBMISSION_IDS]) as $submissionId) {
                $submission = $this->submissionManager->getEntityById($submissionId);
                if ($submission === null) {
                    continue;
                }
                if (!array_key_exists($submission->getSourceBlogId(), $profiles)) {
                    try {
                        $profile = $this->settingsManager->getSingleSettingsProfile($submission->getSourceBlogId());
                    } catch (SmartlingDbException) {
                        $profile = null;
                    }
                    $profiles[$submission->getSourceBlogId()] = $profile;
                }
                $profile = $profiles[$submission->getSourceBlogId()];
                if (!$profile instanceof ConfigurationProfileEntity) {
                    continue;
                }

                $batchUid = $item[UploadQueueEntity::FIELD_BATCH_UID];
                try {
                    $this->api->cancelBatchFile($profile, $batchUid, $submission->getFileUri());
                } catch (SmartlingApiException) {
                    continue;
                }
            }
        }
    }

    private function getSmartlingLocale(SubmissionEntity $submission): ?string
    {
        try {
            return $this->settingsManager->getSmartlingLocaleBySubmission($submission);
        } catch (SmartlingDbException) {
            // profile not found, return null
        }

        return null;
    }

    private function delete(int $id): void
    {
        $block = new ConditionBlock(ConditionBuilder::CONDITION_BLOCK_LEVEL_OPERATOR_AND);
        $block->addCondition(new Condition(ConditionBuilder::CONDITION_SIGN_EQ, UploadQueueEntity::FIELD_ID, $id));

        $this->db->query(QueryBuilder::buildDeleteQuery($this->tableName, $block));
    }
}
