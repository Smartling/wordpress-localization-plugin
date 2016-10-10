<?php
namespace Smartling\DbAl\Migrations;
use Smartling\Settings\ConfigurationProfileEntity;

/**
 * Class Migration161010
 * @package Smartling\DbAl\Migrations
 */
class Migration161010 implements SmartlingDbMigrationInterface
{
    public function getVersion()
    {
        return 161010;
    }

    public function getQueries($tablePrefix = '')
    {
        return [
            vsprintf(
                'ALTER TABLE `%s%s` ADD COLUMN `download_on_change` %s AFTER %s',
                [
                    $tablePrefix,
                    ConfigurationProfileEntity::getTableName(),
                    ConfigurationProfileEntity::DB_TYPE_UINT_SWITCH,
                    'upload_on_update'
                ]
            ),
            vsprintf(
                'ALTER TABLE `%s%s` ADD COLUMN `clean_metadata_on_download` %s AFTER %s',
                [
                    $tablePrefix,
                    ConfigurationProfileEntity::getTableName(),
                    ConfigurationProfileEntity::DB_TYPE_UINT_SWITCH,
                    'download_on_change'
                ]
            ),
        ];
    }
}