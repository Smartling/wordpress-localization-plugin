<?php

namespace Smartling\DbAl\Migrations;

use Smartling\DbAl\DB;
use Smartling\DbAl\UploadQueueManager;

class Migration240315 implements SmartlingDbMigrationInterface
{
    public function getVersion(): int
    {
        return 240315;
    }

    public function getQueries($tablePrefix = 'wp_'): array
    {
        $tableName = (new DB())->completeTableName(UploadQueueManager::TABLE_NAME);
        return [<<<SQL
CREATE TABLE IF NOT EXISTS $tableName
(
    submissionId BIGINT UNSIGNED NOT NULL,
    jobUid TINYTEXT NOT NULL,
    createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL
);
SQL,
            "ALTER TABLE $tableName ADD CONSTRAINT {$tableName}_pk PRIMARY KEY (submissionId, jobUid(12))"
        ];
    }
}
