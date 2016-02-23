<?php
namespace Smartling\DbAl\Migrations;

use Smartling\Bootstrap;

/**
 * Class Migration160222
 *
 * @package Smartling\DbAl\Migrations
 *
 */
class Migration160222 implements SmartlingDbMigrationInterface
{
    public function getVersion()
    {
        return 160222;
    }

    public function getQueries($tablePrefix = '')
    {
        return [
            vsprintf(
                'CREATE TABLE IF NOT EXISTS `%ssmartling_queue` ('
                . '`id` INT(20) UNSIGNED NOT NULL AUTO_INCREMENT,'
                . '`queue` VARCHAR(255) NOT NULL,'
                . '`payload` TEXT NOT NULL DEFAULT \'\','
                . ' PRIMARY KEY (`id`), INDEX(`queue`))',
                [
                    $tablePrefix,
                ]
            ),
        ];
    }
}