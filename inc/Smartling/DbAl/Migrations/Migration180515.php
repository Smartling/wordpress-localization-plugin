<?php

namespace Smartling\DbAl\Migrations;

use Smartling\Submissions\SubmissionEntity;

class Migration180515 implements SmartlingDbMigrationInterface
{
    public function getVersion()
    {
        return 180515;
    }

    public function getQueries($tablePrefix = '')
    {
        return [
            vsprintf(
                'ALTER TABLE `%s%s` ADD COLUMN `locked_fields` %s AFTER %s',
                [
                    $tablePrefix,
                    SubmissionEntity::getTableName(),
                    'TEXT NULL',
                    'batch_uid',
                ]
            ),
        ];
    }
}