<?php

namespace Smartling\Jobs;

use Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapperInterface;
use Smartling\Exception\EntityNotFoundException;
use Smartling\Helpers\ArrayHelper;

class JobInformationManager
{
    private SmartlingToCMSDatabaseAccessWrapperInterface $db;
    private string $tableName;

    public function __construct(SmartlingToCMSDatabaseAccessWrapperInterface $db)
    {
        $this->db = $db;
        $this->tableName = $db->completeTableName(JobInformationEntity::getTableName());
    }

    public function store(JobInformationEntity $jobInfo): JobInformationEntity
    {
        $this->db->query(sprintf(
            "INSERT INTO %s (%s, %s, %s) values ('%s', '%s', '%s')",
            $this->db->completeTableName(JobInformationEntity::getTableName()),
            JobInformationEntity::FIELD_JOB_NAME,
            JobInformationEntity::FIELD_JOB_UID,
            JobInformationEntity::FIELD_PROJECT_UID,
            $jobInfo->getJobName(),
            $jobInfo->getJobUid(),
            $jobInfo->getProjectUid(),
        ));

        return $this->getById($this->db->getLastInsertedId());
    }

    public function getById(int $id): JobInformationEntity
    {
        return $this->getOne($id, JobInformationEntity::FIELD_ID);
    }

    public function getLastNotEmptyBySubmissionId(int $id): ?JobInformationEntity
    {
        try {
            $result = $this->getOne(
                $id,
                JobInformationEntity::FIELD_SUBMISSION_ID,
                sprintf('%s != ""', JobInformationEntity::FIELD_JOB_NAME)
            );
        } catch (EntityNotFoundException $e) {
            return null;
        }
        return $result;
    }

    private function getOne(int $id, string $field, string $where = ''): JobInformationEntity
    {
        if (!array_key_exists($field, JobInformationEntity::getFieldDefinitions())) {
            throw new \InvalidArgumentException("Unable to get job information by field `$field`");
        }
        $result = $this->db->fetch(sprintf(
            'SELECT %s FROM %s WHERE %s=%d %s ORDER BY %s DESC LIMIT 1',
            implode(', ', array_keys(JobInformationEntity::getFieldDefinitions())),
            $this->tableName,
            $field,
            $id,
            $where === '' ? '' : ' AND ' . $where,
            JobInformationEntity::FIELD_ID
        ), 'ARRAY_A');
        if (count($result) === 0) {
            throw new EntityNotFoundException('Unable to get entity');
        }
        $result = ArrayHelper::first($result);
        return new JobInformationEntity(
            $result[JobInformationEntity::FIELD_JOB_NAME],
            $result[JobInformationEntity::FIELD_JOB_UID],
            $result[JobInformationEntity::FIELD_PROJECT_UID],
            (int)$result[JobInformationEntity::FIELD_ID]
        );
    }
}
