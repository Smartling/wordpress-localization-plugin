<?php

namespace Smartling\Jobs;

use Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapperInterface;
use Smartling\Helpers\ArrayHelper;

class JobInformationManager
{
    private $db;

    public function __construct(SmartlingToCMSDatabaseAccessWrapperInterface $db)
    {
        $this->db = $db;
    }

    public function store(JobInformationEntity $jobInfo): JobInformationEntity
    {
        $this->db->query(sprintf(
            "INSERT INTO %s (%s, %s, %s, %s, %s) values (%d, '%s', '%s', '%s', '%s')",
            $this->db->completeTableName(JobInformationEntity::getTableName()),
            JobInformationEntity::FIELD_SUBMISSION_ID,
            JobInformationEntity::FIELD_BATCH_UID,
            JobInformationEntity::FIELD_JOB_NAME,
            JobInformationEntity::FIELD_JOB_UID,
            JobInformationEntity::FIELD_PROJECT_UID,
            $jobInfo->getSubmissionId(),
            $jobInfo->getBatchUid(),
            $jobInfo->getJobName(),
            $jobInfo->getJobUid(),
            $jobInfo->getProjectUid()
        ));

        return $this->getById($this->db->getLastInsertedId());
    }

    public function getById(int $id): JobInformationEntity {
        return $this->getOne($id, JobInformationEntity::FIELD_ID);
    }

    public function getBySubmissionId(int $id): JobInformationEntity {
        return $this->getOne($id, JobInformationEntity::FIELD_SUBMISSION_ID);
    }

    private function getOne(int $id, string $field): JobInformationEntity
    {
        if (!array_key_exists($field, JobInformationEntity::getFieldDefinitions())) {
            throw new \InvalidArgumentException("Unable to get job information by field `$field`");
        }
        $result = $this->db->fetch(sprintf(
            'SELECT %s, %s, %s, %s, %s, %s FROM %s WHERE %s=%d ORDER BY %s DESC LIMIT 1',
            JobInformationEntity::FIELD_ID,
            JobInformationEntity::FIELD_SUBMISSION_ID,
            JobInformationEntity::FIELD_BATCH_UID,
            JobInformationEntity::FIELD_JOB_NAME,
            JobInformationEntity::FIELD_JOB_UID,
            JobInformationEntity::FIELD_PROJECT_UID,
            $this->db->completeTableName(JobInformationEntity::getTableName()),
            $field,
            $id,
            JobInformationEntity::FIELD_ID
        ), 'ARRAY_A');
        if ($result === false) {
            throw new \RuntimeException('Unable to get entity');
        }
        $result = ArrayHelper::first($result);
        return new JobInformationEntity(
            $result[JobInformationEntity::FIELD_BATCH_UID],
            $result[JobInformationEntity::FIELD_JOB_NAME],
            $result[JobInformationEntity::FIELD_JOB_UID],
            $result[JobInformationEntity::FIELD_PROJECT_UID],
            (int)$result[JobInformationEntity::FIELD_SUBMISSION_ID],
            (int)$result[JobInformationEntity::FIELD_ID]
        );
    }
}
