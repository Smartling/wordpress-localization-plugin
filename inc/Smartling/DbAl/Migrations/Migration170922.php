<?php

namespace Smartling\DbAl\Migrations;

use Smartling\Submissions\SubmissionEntity;


class Migration170922 implements SmartlingDbMigrationInterface
{
    public function getVersion()
    {
        return 170922;
    }

    public function getQueries($tablePrefix = '')
    {
        return [
            vsprintf(
                'ALTER TABLE `%s%s` ADD COLUMN `job_id` %s AFTER %s',
                [
                    $tablePrefix,
                    SubmissionEntity::getTableName(),
                    SubmissionEntity::DB_TYPE_STRING_64 . ' ' . SubmissionEntity::DB_TYPE_DEFAULT_EMPTYSTRING,
                    'last_error',
                ]
            ),
        ];
    }
}