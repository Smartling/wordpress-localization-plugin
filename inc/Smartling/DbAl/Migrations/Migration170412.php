<?php
namespace Smartling\DbAl\Migrations;
use Smartling\Settings\ConfigurationProfileEntity;


class Migration170412 implements SmartlingDbMigrationInterface
{
    public function getVersion()
    {
        return 170412;
    }

    public function getQueries($tablePrefix = '')
    {
        return [
            vsprintf(
                'ALTER TABLE `%s%s` ADD COLUMN `publish_completed` %s AFTER %s',
                [
                    $tablePrefix,
                    ConfigurationProfileEntity::getTableName(),
                    ConfigurationProfileEntity::DB_TYPE_UINT_SWITCH_ON,
                    'upload_on_update'
                ]
            ),
        ];
    }
}