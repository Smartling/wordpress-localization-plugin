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
        $this->db->query(sprintf(
            'INSERT INTO %1$s (%2$s, %3$s, %4$s, %5$s, %6$s) values (\'%7$s\', \'%8$s\', \'%9$s\', \'%10$s\', \'%11$s\') ON DUPLICATE KEY UPDATE %2$s = \'%7$s\', %6$s = \'%11$s\'',
            $this->db->completeTableName(JobEntity::getTableName()),
            JobEntity::FIELD_JOB_NAME,
            JobEntity::FIELD_JOB_UID,
            JobEntity::FIELD_PROJECT_UID,
            JobEntity::FIELD_CREATED,
            JobEntity::FIELD_MODIFIED,
            $this->db->escape($jobInfo->getJobName()),
            $this->db->escape($jobInfo->getJobUid()),
            $this->db->escape($jobInfo->getProjectUid()),
            DateTimeHelper::dateTimeToString($jobInfo->getCreated()),
            DateTimeHelper::dateTimeToString($jobInfo->getModified()),
        ));

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
        $result = $this->db->fetch(sprintf(
            'SELECT %s FROM %s WHERE %s = \'%s\' ORDER BY %s DESC LIMIT 1',
            implode(', ', array_keys(JobEntity::getFieldDefinitions())),
            $this->tableName,
            $field,
            $this->db->escape($value),
            JobEntity::FIELD_ID
        ), 'ARRAY_A');
        if (count($result) === 0) {
            throw new EntityNotFoundException("Unable to get entity by `$field` = $value");
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
