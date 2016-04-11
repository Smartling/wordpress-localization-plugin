<?php
namespace Smartling\DbAl\Migrations;

use Smartling\Base\SmartlingEntityAbstract;


/**
 * Class Migration160411
 * @package Smartling\DbAl\Migrations
 */
class Migration160411 implements SmartlingDbMigrationInterface
{
    public function getVersion()
    {
        return 160411;
    }

    public function getQueries($tablePrefix = '')
    {
        return [
            vsprintf('DROP TABLE IF EXISTS `%ssmartling_queue`',
                     [
                         $tablePrefix,
                     ]
            ),
            vsprintf(
                'CREATE TABLE IF NOT EXISTS `%ssmartling_queue` ('
                . '`id` ' . SmartlingEntityAbstract::DB_TYPE_U_BIGINT . ' ' . SmartlingEntityAbstract::DB_TYPE_INT_MODIFIER_AUTOINCREMENT . ','
                . '`queue` ' . SmartlingEntityAbstract::DB_TYPE_STRING_64 . ','
                . '`payload` ' . SmartlingEntityAbstract::DB_TYPE_STRING_TEXT . ','
                . '`payload_hash` ' . SmartlingEntityAbstract::DB_TYPE_HASH_MD5 . ','
                . ' PRIMARY KEY (`id`), UNIQUE(`queue`, `payload_hash`))',
                [
                    $tablePrefix,
                ]
            ),
        ];
    }
}