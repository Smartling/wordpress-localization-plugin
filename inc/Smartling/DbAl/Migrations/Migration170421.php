<?php

namespace Smartling\DbAl\Migrations;

use Smartling\Submissions\SubmissionEntity;


class Migration170421 implements SmartlingDbMigrationInterface
{
    public function getVersion()
    {
        return 170421;
    }

    public function getQueries($tablePrefix = '')
    {
        return [
            vsprintf(
                'ALTER TABLE `%s%s` ADD COLUMN `excluded_string_count` %s AFTER %s',
                [
                    $tablePrefix,
                    SubmissionEntity::getTableName(),
                    SubmissionEntity::DB_TYPE_U_BIGINT . ' ' . SubmissionEntity::DB_TYPE_DEFAULT_ZERO,
                    'completed_string_count',
                ]
            ),
            vsprintf(
                'ALTER TABLE `%s%s` ADD COLUMN `total_string_count` %s AFTER %s',
                [
                    $tablePrefix,
                    SubmissionEntity::getTableName(),
                    SubmissionEntity::DB_TYPE_U_BIGINT . ' ' . SubmissionEntity::DB_TYPE_DEFAULT_ZERO,
                    'excluded_string_count',
                ]
            ),
        ];
    }
}