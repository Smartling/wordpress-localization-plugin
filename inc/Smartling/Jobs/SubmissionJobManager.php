<?php

namespace Smartling\Jobs;

use Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapperInterface;
use Smartling\Exception\EntityNotFoundException;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\DateTimeHelper;

class SubmissionJobManager
{
    private SmartlingToCMSDatabaseAccessWrapperInterface $db;
    private string $tableName;

    public function __construct(SmartlingToCMSDatabaseAccessWrapperInterface $db)
    {
        $this->db = $db;
        $this->tableName = $db->completeTableName(SubmissionJobEntity::getTableName());
    }

    public function store(SubmissionJobEntity $submissionJobEntity): SubmissionJobEntity
    {
        echo "SJE" . json_encode($submissionJobEntity, JSON_THROW_ON_ERROR);
        $this->db->query(sprintf(
            'INSERT INTO %1$s (%2$s, %3$s, %4$s, %5$s) values (%6$d, %7$d, \'%8$s\', \'%9$s\') ON DUPLICATE KEY UPDATE %3$s = %7$d, %5$s = \'%9$s\'',
            $this->db->completeTableName(SubmissionJobEntity::getTableName()),
            SubmissionJobEntity::FIELD_SUBMISSION_ID,
            SubmissionJobEntity::FIELD_JOB_ID,
            SubmissionJobEntity::FIELD_CREATED,
            SubmissionJobEntity::FIELD_MODIFIED,
            $submissionJobEntity->getSubmissionId(),
            $submissionJobEntity->getJobId(),
            DateTimeHelper::dateTimeToString($submissionJobEntity->getCreated()),
            DateTimeHelper::dateTimeToString($submissionJobEntity->getModified()),
        ));

        echo json_encode(sprintf(
            'INSERT INTO %1$s (%2$s, %3$s, %4$s, %5$s) values (%6$d, %7$d, \'%8$s\', \'%9$s\') ON DUPLICATE KEY UPDATE %3$s = %7$d, %5$s = \'%9$s\'',
            $this->db->completeTableName(SubmissionJobEntity::getTableName()),
            SubmissionJobEntity::FIELD_SUBMISSION_ID,
            SubmissionJobEntity::FIELD_JOB_ID,
            SubmissionJobEntity::FIELD_CREATED,
            SubmissionJobEntity::FIELD_MODIFIED,
            $submissionJobEntity->getSubmissionId(),
            $submissionJobEntity->getJobId(),
            DateTimeHelper::dateTimeToString($submissionJobEntity->getCreated()),
            DateTimeHelper::dateTimeToString($submissionJobEntity->getModified()),
        ), JSON_THROW_ON_ERROR);

        $id = $this->db->getLastInsertedId();
        echo $id;
        echo $this->db->getLastErrorMessage();

        return $this->getOne($this->db->getLastInsertedId(), SubmissionJobEntity::FIELD_ID);
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
        $result = $this->db->fetch(sprintf(
            'SELECT %s FROM %s WHERE %s = %d ORDER BY %s DESC LIMIT 1',
            implode(', ', array_keys(SubmissionJobEntity::getFieldDefinitions())),
            $this->tableName,
            $field,
            $id,
            JobInformationEntity::FIELD_ID
        ), 'ARRAY_A');
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
