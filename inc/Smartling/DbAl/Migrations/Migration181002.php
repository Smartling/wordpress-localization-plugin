<?php

namespace Smartling\DbAl\Migrations;


use Smartling\Settings\ConfigurationProfileEntity;

class Migration181002 implements SmartlingDbMigrationInterface
{
    public function getVersion()
    {
        return 181002;
    }

    public function getQueries($tablePrefix = '')
    {
        return [
            vsprintf(
                'ALTER TABLE `%s%s` ADD COLUMN `enable_notifications` %s AFTER %s',
                [
                    $tablePrefix,
                    ConfigurationProfileEntity::getTableName(),
                    ConfigurationProfileEntity::DB_TYPE_UINT_SWITCH . ' ' .
                    ConfigurationProfileEntity::DB_TYPE_DEFAULT_ZERO,
                    'clone_attachment',
                ]
            ),
        ];
    }
}