<?php

namespace Smartling\DbAl\Migrations;

use Smartling\Settings\ConfigurationProfileEntity;

class Migration200528 implements SmartlingDbMigrationInterface
{
    public function getVersion()
    {
        return 200528;
    }

    public function getQueries($tablePrefix = '')
    {
        return [
            vsprintf(
                'ALTER TABLE `%s%s` ADD COLUMN `filter_field_name_regexp` %s AFTER %s',
                [
                    $tablePrefix,
                    ConfigurationProfileEntity::getTableName(),
                    ConfigurationProfileEntity::DB_TYPE_UINT_SWITCH . ' ' .
                    ConfigurationProfileEntity::DB_TYPE_DEFAULT_ZERO,
                    'enable_notifications',
                ]
            ),
        ];
    }
}