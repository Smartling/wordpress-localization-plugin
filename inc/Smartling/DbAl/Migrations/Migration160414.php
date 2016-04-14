<?php
namespace Smartling\DbAl\Migrations;
use Smartling\Settings\ConfigurationProfileEntity;

/**
 * Class Migration160414
 * @package Smartling\DbAl\Migrations
 */
class Migration160414 implements SmartlingDbMigrationInterface
{
    public function getVersion()
    {
        return 160414;
    }

    public function getQueries($tablePrefix = '')
    {
        return [
            vsprintf(
                'ALTER TABLE `%ssmartling_configuration_profiles` ADD COLUMN `upload_on_update` %s AFTER `retrieval_type`',
                [
                    $tablePrefix,
                    ConfigurationProfileEntity::DB_TYPE_UINT_SWITCH
                ]
            ),
            vsprintf(
                'UPDATE `%ssmartling_configuration_profiles` SET `upload_on_update` = 1',
                [
                    $tablePrefix,
                ]
            ),
            vsprintf(
                'ALTER TABLE `%ssmartling_submissions` ADD COLUMN `outdated` %s',
                [
                    $tablePrefix,
                    ConfigurationProfileEntity::DB_TYPE_UINT_SWITCH
                ]
            ),
        ];
    }
}