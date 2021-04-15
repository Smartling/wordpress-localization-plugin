<?php

namespace Smartling\Jobs;

use Smartling\Base\SmartlingEntityAbstract;
use Smartling\SmartlingTableDefinitionInterface;

class JobInformationEntity implements SmartlingTableDefinitionInterface
{
    public const FIELD_BATCH_UID = 'batch_uid';
    public const FIELD_ID = 'id';
    public const FIELD_JOB_NAME = 'job_name';
    public const FIELD_JOB_UID = 'job_uid';
    public const FIELD_PROJECT_UID = 'project_uid';
    public const FIELD_SUBMISSION_ID = 'submission_id';

    private string $batchUid;
    private ?int $id;
    private string $jobName;
    private string $jobUid;
    private string $projectUid;
    private ?int $submissionId;

    public function __construct(string $batchUid, string $jobName, string $jobUid, string $projectUid, int $submissionId = null, int $id = null)
    {
        $this->batchUid = $batchUid;
        $this->id = $id;
        $this->jobName = $jobName;
        $this->jobUid = $jobUid;
        $this->projectUid = $projectUid;
        $this->submissionId = $submissionId;
    }

    public function getBatchUid(): string
    {
        return $this->batchUid;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getJobName(): string
    {
        return $this->jobName;
    }

    public function getJobUid(): string
    {
        return $this->jobUid;
    }

    public function getProjectUid(): string
    {
        return $this->projectUid;
    }

    public function getSubmissionId(): ?int
    {
        return $this->submissionId;
    }

    public function setSubmissionId(int $submissionId): self
    {
        $result = clone $this;
        $result->submissionId = $submissionId;

        return $result;
    }

    public static function getFieldLabels(): array
    {
        return [
            self::FIELD_ID => __('ID'),
            self::FIELD_SUBMISSION_ID => __('Submission ID'),
            self::FIELD_BATCH_UID => __('Batch UID'),
            self::FIELD_JOB_NAME => __('Job Name'),
            self::FIELD_JOB_UID => __('Job UID'),
            self::FIELD_PROJECT_UID => __('Project UID'),
        ];
    }

    public static function getFieldDefinitions(): array
    {
        return [
            self::FIELD_ID => SmartlingEntityAbstract::DB_TYPE_U_BIGINT . ' ' . SmartlingEntityAbstract::DB_TYPE_INT_MODIFIER_AUTOINCREMENT,
            self::FIELD_SUBMISSION_ID => SmartlingEntityAbstract::DB_TYPE_U_BIGINT . ' ' . SmartlingEntityAbstract::DB_TYPE_DEFAULT_ZERO,
            self::FIELD_BATCH_UID => SmartlingEntityAbstract::DB_TYPE_STRING_64 . ' ' . SmartlingEntityAbstract::DB_TYPE_DEFAULT_EMPTYSTRING,
            self::FIELD_JOB_NAME => SmartlingEntityAbstract::DB_TYPE_STRING_STANDARD . ' ' . SmartlingEntityAbstract::DB_TYPE_DEFAULT_EMPTYSTRING,
            self::FIELD_JOB_UID => SmartlingEntityAbstract::DB_TYPE_STRING_64 . ' ' . SmartlingEntityAbstract::DB_TYPE_DEFAULT_EMPTYSTRING,
            self::FIELD_PROJECT_UID => SmartlingEntityAbstract::DB_TYPE_STRING_64 . ' ' . SmartlingEntityAbstract::DB_TYPE_DEFAULT_EMPTYSTRING,
        ];
    }

    public static function getSortableFields(): array
    {
        return [
            self::FIELD_ID,
            self::FIELD_SUBMISSION_ID,
            self::FIELD_BATCH_UID,
            self::FIELD_JOB_NAME,
            self::FIELD_JOB_UID,
            self::FIELD_PROJECT_UID,
        ];
    }

    public static function getIndexes(): array
    {
        return [
            [
                'type' => 'primary',
                'columns' => [self::FIELD_ID],
            ],
            [
                'type' => 'index',
                'columns' => [self::FIELD_SUBMISSION_ID],
            ],
        ];
    }

    public static function getTableName(): string
    {
        return 'smartling_jobs';
    }

    public static function getFieldLabel($fieldName)
    {
        $labels = self::getFieldLabels();

        return array_key_exists($fieldName, $labels) ? $labels[$fieldName] : $fieldName;
    }
}
