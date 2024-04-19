<?php

namespace Smartling\Models;

use Smartling\Base\SmartlingEntityAbstract;
use Smartling\SmartlingTableDefinitionInterface;

class UploadQueueEntity implements SmartlingTableDefinitionInterface
{
    public const FIELD_ID = 'id';
    public const FIELD_BATCH_UID = 'batch_uid';
    public const FIELD_CREATED = 'created';
    public const FIELD_SUBMISSION_IDS = 'submission_ids';
    public const TABLE_NAME = 'smartling_upload_queue';

    public function __construct(private  $submissionId, private string $batchUid)
    {
    }

    public function getBatchUid(): string
    {
        return $this->batchUid;
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
            self::FIELD_SUBMISSION_IDS => SmartlingEntityAbstract::DB_TYPE_STRING_TEXT,
            self::FIELD_BATCH_UID => SmartlingEntityAbstract::DB_TYPE_STRING_64 . ' ' . SmartlingEntityAbstract::DB_TYPE_DEFAULT_EMPTYSTRING,
            self::FIELD_CREATED => SmartlingEntityAbstract::DB_TYPE_DATETIME,
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
        ];
    }

    public static function getTableName(): string
    {
        return self::TABLE_NAME;
    }

    public static function getFieldLabel($fieldName): string
    {
        return self::getFieldLabels()[$fieldName] ?? $fieldName;
    }
}
