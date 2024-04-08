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
use Smartling\Models\IntStringPair;
use Smartling\Models\IntStringPairCollection;
use Smartling\Models\UploadQueueEntity;
use Smartling\Models\UploadQueueItem;
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
            UploadQueueEntity::FIELD_BATCH_UID,
            UploadQueueEntity::FIELD_SUBMISSION_ID,
        ]))) !== null) {
            $batchUid = $result[UploadQueueEntity::FIELD_BATCH_UID];
            $submissionId = $result[UploadQueueEntity::FIELD_SUBMISSION_ID];
            $submission = $this->submissionManager->getEntityById($submissionId);

            if ($submission === null) {
                $this->deleteSubmission($submissionId, 'Unable to get submission entity');
                continue;
            }

            $locale = $this->getSmartlingLocale($submission);
            if ($locale === null) {
                $this->deleteSubmission($submissionId, 'Unable to get Smartling locale');
                continue;
            }

            $locales = new IntStringPairCollection([$submission->getId() => $locale]);
            $submissions = [$submission];

            foreach ($this->submissionManager->getWithSameSource($submission) as $submission) {
                if (count($this->findIds($this->getConditionBlock($batchUid, $submission->getId()))) > 0) {
                    $locale = $this->getSmartlingLocale($submission);
                    if ($locale === null) {
                        $this->deleteSubmission($submission->getId(), 'Unable to get Smartling locale');
                        continue;
                    }
                    $locales = $locales->add([new IntStringPair($submission->getId(), $locale)]);
                    $submissions[] = $submission;
                }
            }

            $item = new UploadQueueItem($submissions, $batchUid, $locales);
            $this->delete($item);

            return $item;
        }

        return null;
    }

    /**
     * @param UploadQueueEntity[] $items
     */
    public function enqueue(array $items): void
    {
        $this->db->query('START TRANSACTION');
        try {
            foreach ($items as $item) {
                assert($item instanceof UploadQueueEntity);
                $this->db->query(QueryBuilder::buildInsertQuery($this->tableName, [
                    UploadQueueEntity::FIELD_BATCH_UID => $item->getBatchUid(),
                    UploadQueueEntity::FIELD_CREATED => DateTimeHelper::nowAsString(),
                    UploadQueueEntity::FIELD_SUBMISSION_ID => $item->getSubmissionId(),
                ], true));
            }
            $this->db->query('COMMIT');
        } catch (\Throwable $e) {
            $this->db->query('ROLLBACK');
            throw $e;
        }
    }

    public function purge(): void
    {
        foreach ($this->db->getRowArray(QueryBuilder::buildSelectQuery($this->tableName, [
            UploadQueueEntity::FIELD_BATCH_UID,
            UploadQueueEntity::FIELD_SUBMISSION_ID,
        ])) as $item) {
            $submission = $this->submissionManager->getEntityById($item[UploadQueueEntity::FIELD_SUBMISSION_ID]);
            if ($submission === null) {
                continue;
            }
            try {
                $profile = $this->settingsManager->getSingleSettingsProfile($submission->getSourceBlogId());
            } catch (SmartlingDbException) {
                continue;
            }
            $batchUid = $item[UploadQueueEntity::FIELD_BATCH_UID];
            try {
                $this->api->cancelBatchFile($profile, $batchUid, $submission->getFileUri());
            } catch (SmartlingApiException) {
                continue;
            }
            $locale = $this->getSmartlingLocale($submission);
            if ($locale === null) {
                $this->deleteSubmission($submission->getId(), 'Queue purge');
            } else {
                $this->delete(new UploadQueueItem([$submission], $batchUid, new IntStringPairCollection([$submission->getId() => $locale])));
            }
        }
        $this->db->query("TRUNCATE $this->tableName");
    }

    private function deleteSubmission(int $submissionId, string $reason): void
    {
        $block = new ConditionBlock(ConditionBuilder::CONDITION_BLOCK_LEVEL_OPERATOR_AND);
        $block->addCondition(new Condition(ConditionBuilder::CONDITION_SIGN_EQ, UploadQueueEntity::FIELD_SUBMISSION_ID, $submissionId));
        $this->db->query(QueryBuilder::buildDeleteQuery($this->tableName, $block));
        $this->getLogger()->debug("Deleted submissionId from upload queue: $reason");
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

    private function delete(UploadQueueItem $item): void
    {
        $block = new ConditionBlock(ConditionBuilder::CONDITION_BLOCK_LEVEL_OPERATOR_AND);
        $block->addCondition(new Condition(ConditionBuilder::CONDITION_SIGN_EQ, UploadQueueEntity::FIELD_BATCH_UID, $item->getBatchUid()));
        $block->addCondition(new Condition(ConditionBuilder::CONDITION_SIGN_IN, UploadQueueEntity::FIELD_SUBMISSION_ID, array_map(static function (SubmissionEntity $submission) {
            return $submission->getId();
        }, $item->getSubmissions())));
        $query = QueryBuilder::buildDeleteQuery($this->tableName, $block);
        $this->db->query($query);
    }

    private function findIds(ConditionBlock $block): ?array
    {
        return $this->db->getRowArray(QueryBuilder::buildSelectQuery(
            $this->tableName,
            [UploadQueueEntity::FIELD_SUBMISSION_ID],
            $block,
        ));
    }

    private function getConditionBlock(string $batchUid, int $submissionId): ConditionBlock
    {
        $block = new ConditionBlock(ConditionBuilder::CONDITION_BLOCK_LEVEL_OPERATOR_AND);
        $block->addCondition(new Condition(ConditionBuilder::CONDITION_SIGN_EQ, UploadQueueEntity::FIELD_BATCH_UID, $batchUid));
        $block->addCondition(new Condition(ConditionBuilder::CONDITION_SIGN_EQ, UploadQueueEntity::FIELD_SUBMISSION_ID, $submissionId));

        return $block;
    }
}
