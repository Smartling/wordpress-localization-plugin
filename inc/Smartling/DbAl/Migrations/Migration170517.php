<?php

namespace Smartling\DbAl\Migrations;

use Smartling\Submissions\SubmissionEntity;


class Migration170517 implements SmartlingDbMigrationInterface
{
    public function getVersion()
    {
        return 170517;
    }

    public function getQueries($tablePrefix = '')
    {
        return [
            vsprintf(
                'ALTER TABLE `%s%s` ADD COLUMN `is_cloned` %s AFTER %s',
                [
                    $tablePrefix,
                    SubmissionEntity::getTableName(),
                    SubmissionEntity::DB_TYPE_UINT_SWITCH . ' ' . SubmissionEntity::DB_TYPE_DEFAULT_ZERO,
                    'is_locked',
                ]
            ),
            vsprintf(
                'UPDATE `%s%s` SET `is_cloned` = 1, `status` = \'%s\' WHERE `status` = \'%s\'',
                [
                    $tablePrefix,
                    SubmissionEntity::getTableName(),
                    SubmissionEntity::SUBMISSION_STATUS_COMPLETED,
                    'Cloned',
                ]
            ),
        ];
    }
}