<?php
namespace Smartling\DbAl\Migrations;

use Smartling\Base\SmartlingEntityAbstract;


/**
 * Class Migration160223
 *
 * @package Smartling\DbAl\Migrations
 *
 */
class Migration160223 implements SmartlingDbMigrationInterface
{
    public function getVersion()
    {
        return 160223;
    }

    public function getQueries($tablePrefix = '')
    {
        return [
            vsprintf(
                'ALTER TABLE `%ssmartling_submissions` ADD COLUMN `last_modified` %s',
                [
                    $tablePrefix,
                    SmartlingEntityAbstract::DB_TYPE_DATETIME
                ]
            ),
        ];
    }
}