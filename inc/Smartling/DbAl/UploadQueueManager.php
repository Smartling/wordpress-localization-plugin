<?php

namespace Smartling\DbAl;

use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\DateTimeHelper;
use Smartling\Helpers\QueryBuilder\Condition\Condition;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBlock;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBuilder;
use Smartling\Helpers\QueryBuilder\QueryBuilder;
use Smartling\Models\UploadQueueItem;

class UploadQueueManager {
    private string $tableName;
    public function __construct(private SmartlingToCMSDatabaseAccessWrapperInterface $db)
    {
        $this->tableName = $db->completeTableName(UploadQueueItem::TABLE_NAME);
    }

    public function count(): int
    {
        return (int)$this->db->getRowArray("SELECT COUNT(*) cnt from $this->tableName")['cnt'];
    }

    public function dequeue(): ?UploadQueueItem
    {
        $result = $this->db->getRowArray(QueryBuilder::buildSelectQuery($this->tableName, [
            UploadQueueItem::FIELD_JOB_UID,
            UploadQueueItem::FIELD_SUBMISSION_ID,
        ]));
        if ($result === null) {
            return null;
        }

        $item = new UploadQueueItem(
            $result[UploadQueueItem::FIELD_SUBMISSION_ID],
            $result[UploadQueueItem::FIELD_JOB_UID],
        );
        $conditionBlock = new ConditionBlock(ConditionBuilder::CONDITION_BLOCK_LEVEL_OPERATOR_AND);
        $conditionBlock->addCondition(new Condition(ConditionBuilder::CONDITION_SIGN_EQ, UploadQueueItem::FIELD_JOB_UID, $item->getJobUid()));
        $conditionBlock->addCondition(new Condition(ConditionBuilder::CONDITION_SIGN_EQ, UploadQueueItem::FIELD_SUBMISSION_ID, $item->getSubmissionId()));
        $this->db->query(QueryBuilder::buildDeleteQuery($this->tableName, $conditionBlock));

        return $item;
    }

    public function enqueue(UploadQueueItem $item): void
    {
        $this->db->query(QueryBuilder::buildInsertQuery($this->tableName, [
            UploadQueueItem::FIELD_CREATED => DateTimeHelper::nowAsString(),
            UploadQueueItem::FIELD_JOB_UID => $item->getJobUid(),
            UploadQueueItem::FIELD_SUBMISSION_ID => $item->getSubmissionId(),
        ], true));
    }

    public function filterSubmissionIdsInJob(array $submissionIds, string $jobUid): array
    {
        $conditionBlock = new ConditionBlock(ConditionBuilder::CONDITION_BLOCK_LEVEL_OPERATOR_AND);
        $conditionBlock->addCondition(new Condition(ConditionBuilder::CONDITION_SIGN_EQ, UploadQueueItem::FIELD_JOB_UID, $jobUid));

        return array_intersect(ArrayHelper::toArrayOfIntegers($this->db->getColumnArray(
            QueryBuilder::buildSelectQuery($this->tableName, [UploadQueueItem::FIELD_SUBMISSION_ID], $conditionBlock))
        ), $submissionIds);
    }

    public function purge(): void
    {
        $this->db->query("TRUNCATE $this->tableName");
    }
}
