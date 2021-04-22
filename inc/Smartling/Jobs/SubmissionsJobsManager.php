<?php

namespace Smartling\Jobs;

use Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapperInterface;
use Smartling\Exception\EntityNotFoundException;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\DateTimeHelper;

class SubmissionsJobsManager
{
    private SmartlingToCMSDatabaseAccessWrapperInterface $db;
    private string $tableName;

    public function __construct(SmartlingToCMSDatabaseAccessWrapperInterface $db)
    {
        $this->db = $db;
        $this->tableName = $db->completeTableName(SubmissionJobEntity::getTableName());
    }

    public function deleteBySubmissionId(int $id): void {
        $this->db->queryPrepared("DELETE FROM $this->tableName WHERE " . SubmissionJobEntity::FIELD_SUBMISSION_ID . " = %d", $id);
    }

    public function store(SubmissionJobEntity $submissionJobEntity): SubmissionJobEntity
    {
        $submissionId = SubmissionJobEntity::FIELD_SUBMISSION_ID;
        $jobId = SubmissionJobEntity::FIELD_JOB_ID;
        $created = SubmissionJobEntity::FIELD_CREATED;
        $modified = SubmissionJobEntity::FIELD_MODIFIED;
        $sql = <<<SQL
INSERT INTO $this->tableName ($submissionId, $jobId, $created, $modified)
    VALUES (%d, %d, %s, %s)
    ON DUPLICATE KEY UPDATE $jobId = %d, $modified = %s
SQL;

        $this->db->queryPrepared(
            $sql,
            $submissionJobEntity->getSubmissionId(),
            $submissionJobEntity->getJobId(),
            DateTimeHelper::dateTimeToString($submissionJobEntity->getCreated()),
            DateTimeHelper::dateTimeToString($submissionJobEntity->getModified()),
            $submissionJobEntity->getJobId(),
            DateTimeHelper::dateTimeToString($submissionJobEntity->getModified()),
        );

        $id = $this->db->getLastInsertedId();
        if ($id === 0) {
            return $this->getOne($submissionJobEntity->getSubmissionId(), SubmissionJobEntity::FIELD_SUBMISSION_ID);
        }
        return $this->getOne($id, SubmissionJobEntity::FIELD_ID);
    }

    public function findJobIdBySubmissionId(int $id): ?int
    {
        try {
            $result = $this->getOne($id, SubmissionJobEntity::FIELD_SUBMISSION_ID)->getJobId();
        } catch (EntityNotFoundException $e) {
            return null;
        }
        return $result;
    }

    /**
     * @throws EntityNotFoundException
     */
    private function getOne(int $id, string $field): SubmissionJobEntity
    {
        if (!array_key_exists($field, SubmissionJobEntity::getFieldDefinitions())) {
            throw new \InvalidArgumentException("Unable to get entity by field `$field`");
        }
        $fields = implode(', ', array_keys(SubmissionJobEntity::getFieldDefinitions()));
        $fieldId = SubmissionJobEntity::FIELD_ID;
        $result = $this->db->fetchPrepared(
            "SELECT $fields FROM {$this->tableName} WHERE $field = %d ORDER BY $fieldId DESC LIMIT 1",
            $id,
        );
        if (count($result) === 0) {
            throw new EntityNotFoundException('Unable to get entity');
        }
        $result = ArrayHelper::first($result);
        return new SubmissionJobEntity(
            $result[SubmissionJobEntity::FIELD_JOB_ID],
            $result[SubmissionJobEntity::FIELD_SUBMISSION_ID],
            $result[SubmissionJobEntity::FIELD_ID],
            DateTimeHelper::stringToDateTime($result[SubmissionJobEntity::FIELD_CREATED]),
            DateTimeHelper::stringToDateTime($result[SubmissionJobEntity::FIELD_MODIFIED]),
        );
    }
}
