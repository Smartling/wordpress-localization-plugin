<?php

namespace Smartling\DbAl\Migrations;

use Smartling\Base\SmartlingEntityAbstract;
use Smartling\DbAl\DB;
use Smartling\Submissions\SubmissionEntity;

class Migration220701 implements SmartlingDbMigrationInterface
{
    public function __construct(private DB $db)
    {
    }
    public function getVersion(): int
    {
        return 220701;
    }

    public function getQueries($tablePrefix = 'wp_'): array
    {
        $tableName = $this->db->completeTableName(SubmissionEntity::getTableName());
        $columnName = SubmissionEntity::FIELD_CREATED_AT;
        return [
            "ALTER TABLE $tableName ADD $columnName " . SmartlingEntityAbstract::DB_TYPE_DATETIME,
            "UPDATE $tableName SET $columnName = " . SubmissionEntity::FIELD_SUBMISSION_DATE,
        ];
    }
}
