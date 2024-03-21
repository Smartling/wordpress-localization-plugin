<?php

namespace Smartling\DbAl;

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

    public function enqueue(UploadQueueItem $item): void
    {
        $this->db->query(QueryBuilder::buildInsertQuery($this->tableName, [
            'jobUid' => $item->getJobUid(),
            'submissionId' => $item->getSubmissionId(),
        ], true));
    }

    public function dequeue(): ?UploadQueueItem
    {
        $result = $this->db->getRowArray(QueryBuilder::buildSelectQuery($this->tableName, ['jobUid', 'submissionId']));
        if ($result === null) {
            return null;
        }

        $item = new UploadQueueItem($result['submissionId'], $result['jobUid']);
        $conditionBlock = new ConditionBlock(ConditionBuilder::CONDITION_BLOCK_LEVEL_OPERATOR_AND);
        $conditionBlock->addCondition(new Condition(ConditionBuilder::CONDITION_SIGN_EQ, 'jobUid', $item->getJobUid()));
        $conditionBlock->addCondition(new Condition(ConditionBuilder::CONDITION_SIGN_EQ, 'submissionId', $item->getSubmissionId()));
        $this->db->query(QueryBuilder::buildDeleteQuery($this->tableName, $conditionBlock));

        return $item;
    }

    public function purge(): void
    {
        $this->db->query("TRUNCATE $this->tableName");
    }
}
