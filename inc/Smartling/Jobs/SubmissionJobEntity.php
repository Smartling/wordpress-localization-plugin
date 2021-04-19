<?php

namespace Smartling\Jobs;

use Smartling\Base\SmartlingEntityAbstract;
use Smartling\SmartlingTableDefinitionInterface;

class SubmissionJobEntity implements SmartlingTableDefinitionInterface
{
    public const FIELD_CREATED = 'created';
    public const FIELD_ID = 'id';
    public const FIELD_JOB_ID = 'job_id';
    public const FIELD_MODIFIED = 'modified';
    public const FIELD_SUBMISSION_ID = 'submission_id';

    private \DateTime $created;
    private ?int $id;
    private int $jobId;
    private \DateTime $modified;
    private int $submissionId;

    public function __construct(int $jobId, int $submissionId, int $id = null, ?\DateTime $created = null, ?\DateTime $modified = null)
    {
        $this->created = $created ?? new \DateTime();
        $this->id = $id;
        $this->jobId = $jobId;
        $this->modified = $modified ?? new \DateTime();
        $this->submissionId = $submissionId;
    }

    public function getCreated(): \DateTime
    {
        return $this->created;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getJobId(): int
    {
        return $this->jobId;
    }

    public function getModified(): \DateTime
    {
        return $this->modified;
    }

    public function getSubmissionId(): int
    {
        return $this->submissionId;
    }

    public static function getFieldLabels(): array
    {
        return [];
    }

    public static function getFieldDefinitions(): array
    {
        return [
            self::FIELD_ID => SmartlingEntityAbstract::DB_TYPE_U_BIGINT . ' ' . SmartlingEntityAbstract::DB_TYPE_INT_MODIFIER_AUTOINCREMENT,
            self::FIELD_JOB_ID => SmartlingEntityAbstract::DB_TYPE_U_BIGINT,
            self::FIELD_SUBMISSION_ID => SmartlingEntityAbstract::DB_TYPE_U_BIGINT,
            self::FIELD_CREATED => SmartlingEntityAbstract::DB_TYPE_DATETIME,
            self::FIELD_MODIFIED => SmartlingEntityAbstract::DB_TYPE_DATETIME,
        ];
    }

    public static function getSortableFields(): array
    {
        return [];
    }

    public static function getIndexes(): array
    {
        return [
            [
                'type' => 'primary',
                'columns' => [self::FIELD_ID],
            ],
            [
                'type' => 'unique',
                'columns' => [self::FIELD_SUBMISSION_ID],
            ],
        ];
    }

    public static function getTableName(): string
    {
        return 'smartling_submissions_jobs';
    }

    public static function getFieldLabel($fieldName)
    {
        $labels = self::getFieldLabels();

        return array_key_exists($fieldName, $labels) ? $labels[$fieldName] : $fieldName;
    }
}
