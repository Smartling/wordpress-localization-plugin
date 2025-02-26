<?php

namespace Smartling\Jobs;

use Smartling\Base\SmartlingEntityAbstract;
use Smartling\SmartlingTableDefinitionInterface;

class JobEntity implements SmartlingTableDefinitionInterface
{
    public const string FIELD_CREATED = 'created';
    public const string FIELD_ID = 'id';
    public const string FIELD_JOB_NAME = 'job_name';
    public const string FIELD_JOB_UID = 'job_uid';
    public const string FIELD_MODIFIED = 'modified';
    public const string FIELD_PROJECT_UID = 'project_uid';

    private \DateTime $created;
    private ?int $id;
    private string $jobName;
    private string $jobUid;
    private \DateTime $modified;
    private string $projectUid;

    public function __construct(string $jobName, string $jobUid, string $projectUid, int $id = null, ?\DateTime $created = null, ?\DateTime $modified = null)
    {
        $this->created = $created ?? new \DateTime();
        $this->id = $id;
        $this->jobName = $jobName;
        $this->jobUid = $jobUid;
        $this->modified = $modified ?? new \DateTime();
        $this->projectUid = $projectUid;
    }

    public function getCreated(): \DateTime
    {
        return $this->created;
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

    public function getModified(): \DateTime
    {
        return $this->modified;
    }

    public function getProjectUid(): string
    {
        return $this->projectUid;
    }

    public static function getFieldLabels(): array
    {
        return [];
    }

    public static function getFieldDefinitions(): array
    {
        return [
            self::FIELD_ID => SmartlingEntityAbstract::DB_TYPE_U_BIGINT . ' ' . SmartlingEntityAbstract::DB_TYPE_INT_MODIFIER_AUTOINCREMENT,
            self::FIELD_JOB_NAME => SmartlingEntityAbstract::DB_TYPE_STRING_STANDARD . ' ' . SmartlingEntityAbstract::DB_TYPE_DEFAULT_EMPTYSTRING,
            self::FIELD_JOB_UID => SmartlingEntityAbstract::DB_TYPE_STRING_64 . ' ' . SmartlingEntityAbstract::DB_TYPE_DEFAULT_EMPTYSTRING,
            self::FIELD_PROJECT_UID => SmartlingEntityAbstract::DB_TYPE_STRING_64 . ' ' . SmartlingEntityAbstract::DB_TYPE_DEFAULT_EMPTYSTRING,
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
                'columns' => [self::FIELD_JOB_UID],
            ]
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

    public static function empty(): self
    {
        return new JobEntity('', '', '');
    }
}
