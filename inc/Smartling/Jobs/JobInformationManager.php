<?php

namespace Smartling\Jobs;

use Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapperInterface;
use Smartling\Exception\EntityNotFoundException;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\DateTimeHelper;

class JobInformationManager
{
    private SmartlingToCMSDatabaseAccessWrapperInterface $db;
    private string $tableName;
    private SubmissionJobManager $submissionJobManager;

    public function __construct(SmartlingToCMSDatabaseAccessWrapperInterface $db, SubmissionJobManager $submissionJobManager)
    {
        $this->db = $db;
        $this->tableName = $db->completeTableName(JobInformationEntity::getTableName());
        $this->submissionJobManager = $submissionJobManager;
    }

    public function store(JobInformationEntity $jobInfo): JobInformationEntity
    {
        $this->db->query(sprintf(
            'INSERT INTO %1$s (%2$s, %3$s, %4$s, %5$s, %6$s) values (\'%7$s\', \'%8$s\', \'%9$s\', \'%10$s\', \'%11$s\') ON DUPLICATE KEY UPDATE %2$s = \'%7$s\', %6$s = \'%11$s\'',
            $this->db->completeTableName(JobInformationEntity::getTableName()),
            JobInformationEntity::FIELD_JOB_NAME,
            JobInformationEntity::FIELD_JOB_UID,
            JobInformationEntity::FIELD_PROJECT_UID,
            JobInformationEntity::FIELD_CREATED,
            JobInformationEntity::FIELD_MODIFIED,
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
    public function getById(int $id): JobInformationEntity
    {
        return $this->getOne($id, JobInformationEntity::FIELD_ID);
    }

    /**
     * @throws EntityNotFoundException if not found
     */
    public function getByJobUid(string $jobUid): JobInformationEntity
    {
        return $this->getOne($jobUid, JobInformationEntity::FIELD_JOB_UID);
    }

    public function getBySubmissionId(int $id): ?JobInformationEntity
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

    private function getOne(string $value, string $field): JobInformationEntity
    {
        if (!array_key_exists($field, JobInformationEntity::getFieldDefinitions())) {
            throw new \InvalidArgumentException("Unable to get job information by field `$field`");
        }
        $result = $this->db->fetch(sprintf(
            'SELECT %s FROM %s WHERE %s = \'%s\' ORDER BY %s DESC LIMIT 1',
            implode(', ', array_keys(JobInformationEntity::getFieldDefinitions())),
            $this->tableName,
            $field,
            $this->db->escape($value),
            JobInformationEntity::FIELD_ID
        ), 'ARRAY_A');
        if (count($result) === 0) {
            throw new EntityNotFoundException('Unable to get entity');
        }
        $result = ArrayHelper::first($result);
        return new JobInformationEntity(
            '',
            $result[JobInformationEntity::FIELD_JOB_NAME],
            $result[JobInformationEntity::FIELD_JOB_UID],
            $result[JobInformationEntity::FIELD_PROJECT_UID],
            (int)$result[JobInformationEntity::FIELD_ID],
            DateTimeHelper::stringToDateTime($result[JobInformationEntity::FIELD_CREATED]),
            DateTimeHelper::stringToDateTime($result[JobInformationEntity::FIELD_MODIFIED]),
        );
    }
}
