<?php

namespace Smartling\DbAl\Migrations;

use Smartling\Settings\ConfigurationProfileEntity;

class Migration180208 implements SmartlingDbMigrationInterface
{
    public function getVersion()
    {
        return 180208;
    }

    public function getQueries($tablePrefix = '')
    {
        return [
            vsprintf(
                'ALTER TABLE `%s%s` ADD COLUMN `clone_attachment` %s AFTER %s',
                [
                    $tablePrefix,
                    ConfigurationProfileEntity::getTableName(),
                    ConfigurationProfileEntity::DB_TYPE_UINT_SWITCH . ' ' . ConfigurationProfileEntity::DB_TYPE_DEFAULT_ZERO,
                    'always_sync_images_on_upload',
                ]
            ),
        ];
    }
}