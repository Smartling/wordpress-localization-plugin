<?php

namespace Smartling\DbAl\Migrations;

use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Submissions\SubmissionEntity;


class Migration170620 implements SmartlingDbMigrationInterface
{
    public function getVersion()
    {
        return 170620;
    }

    public function getQueries($tablePrefix = '')
    {
        return [
            vsprintf(
                'ALTER TABLE `%s%s` ADD COLUMN `always_sync_images_on_upload` %s AFTER %s',
                [
                    $tablePrefix,
                    ConfigurationProfileEntity::getTableName(),
                    SubmissionEntity::DB_TYPE_UINT_SWITCH,
                    'clean_metadata_on_download',
                ]
            ),
        ];
    }
}