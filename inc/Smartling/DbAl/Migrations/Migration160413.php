<?php
namespace Smartling\DbAl\Migrations;

/**
 * Class Migration160413
 * @package Smartling\DbAl\Migrations
 */
class Migration160413 implements SmartlingDbMigrationInterface
{
    public function getVersion()
    {
        return 160413;
    }

    public function getQueries($tablePrefix = '')
    {
        return [
            vsprintf('ALTER TABLE `%ssmartling_configuration_profiles` DROP COLUMN `api_url`', [$tablePrefix]),
        ];
    }
}