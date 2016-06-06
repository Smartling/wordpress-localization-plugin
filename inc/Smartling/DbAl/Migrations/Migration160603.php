<?php
namespace Smartling\DbAl\Migrations;
use Smartling\Settings\ConfigurationProfileEntity;

/**
 * Class Migration160603
 * @package Smartling\DbAl\Migrations
 */
class Migration160603 implements SmartlingDbMigrationInterface
{
    public function getVersion()
    {
        return 160603;
    }

    public function getQueries($tablePrefix = '')
    {
        return [
            vsprintf(
                'ALTER TABLE `%ssmartling_submissions` ADD COLUMN `last_error` %s',
                [
                    $tablePrefix,
                    ConfigurationProfileEntity::DB_TYPE_STRING_TEXT
                ]
            ),
        ];
    }
}