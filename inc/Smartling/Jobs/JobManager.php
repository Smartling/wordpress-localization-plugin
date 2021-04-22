<?php

namespace Smartling\Jobs;

use Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapperInterface;
use Smartling\Exception\EntityNotFoundException;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\DateTimeHelper;

class JobManager
{
    private SmartlingToCMSDatabaseAccessWrapperInterface $db;
    private string $tableName;
    private SubmissionsJobsManager $submissionJobManager;

    public function __construct(SmartlingToCMSDatabaseAccessWrapperInterface $db, SubmissionsJobsManager $submissionJobManager)
    {
        $this->db = $db;
        $this->tableName = $db->completeTableName(JobEntity::getTableName());
        $this->submissionJobManager = $submissionJobManager;
    }

    public function store(JobEntity $jobInfo): JobEntity
    {
        $jobName = JobEntity::FIELD_JOB_NAME;
        $jobUid = JobEntity::FIELD_JOB_UID;
        $projectUid = JobEntity::FIELD_PROJECT_UID;
        $created = JobEntity::FIELD_CREATED;
        $modified = JobEntity::FIELD_MODIFIED;
        $sql = <<<SQL
INSERT INTO $this->tableName ($jobName, $jobUid, $projectUid, $created, $modified)
    VALUES (%s, %s, %s, %s, %s)
    ON DUPLICATE KEY UPDATE $jobName = %s, $modified = %s
SQL;
        $this->db->queryPrepared(
            $sql,
            $jobInfo->getJobName(),
            $jobInfo->getJobUid(),
            $jobInfo->getProjectUid(),
            DateTimeHelper::dateTimeToString($jobInfo->getCreated()),
            DateTimeHelper::dateTimeToString($jobInfo->getModified()),
            $jobInfo->getJobName(),
            DateTimeHelper::dateTimeToString($jobInfo->getModified()),
        );

        $id = $this->db->getLastInsertedId();
        if ($id === 0) {
            return $this->getByJobUid($jobInfo->getJobUid());
        }

        return $this->getById($id);
    }

    /**
     * @throws EntityNotFoundException if not found
     */
    public function getById(int $id): JobEntity
    {
        return $this->getOne($id, JobEntity::FIELD_ID);
    }

    /**
     * @throws EntityNotFoundException if not found
     */
    public function getByJobUid(string $jobUid): JobEntity
    {
        return $this->getOne($jobUid, JobEntity::FIELD_JOB_UID);
    }

    public function getBySubmissionId(int $id): ?JobEntity
    {
        try {
            $jobId = $this->submissionJobManager->findJobIdBySubmissionId($id);
            if ($jobId === null) {
                return null;
            }
            $result = $this->getById($jobId);
        } catch (EntityNotFoundException $e) {
            return null;
        }
        return $result;
    }

    private function getOne(string $value, string $field): JobEntity
    {
        if (!array_key_exists($field, JobEntity::getFieldDefinitions())) {
            throw new \InvalidArgumentException("Unable to get job information by field `$field`");
        }
        $fields = implode(', ', array_keys(JobEntity::getFieldDefinitions()));
        $fieldId = JobEntity::FIELD_ID;
        $result = $this->db->fetchPrepared(
            "SELECT $fields FROM $this->tableName WHERE $field = %s ORDER BY $fieldId DESC LIMIT 1",
            $value,
        );
        if (count($result) === 0) {
            throw new EntityNotFoundException("Unable to get entity where `$field` = $value");
        }
        $result = ArrayHelper::first($result);
        return new JobEntity(
            $result[JobEntity::FIELD_JOB_NAME],
            $result[JobEntity::FIELD_JOB_UID],
            $result[JobEntity::FIELD_PROJECT_UID],
            (int)$result[JobEntity::FIELD_ID],
            DateTimeHelper::stringToDateTime($result[JobEntity::FIELD_CREATED]),
            DateTimeHelper::stringToDateTime($result[JobEntity::FIELD_MODIFIED]),
        );
    }
}
