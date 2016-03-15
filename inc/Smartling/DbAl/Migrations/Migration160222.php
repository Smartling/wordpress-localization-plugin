<?php
namespace Smartling\DbAl\Migrations;

use Smartling\Base\SmartlingEntityAbstract;
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
                . '`id` ' . SmartlingEntityAbstract::DB_TYPE_U_BIGINT . ' ' . SmartlingEntityAbstract::DB_TYPE_INT_MODIFIER_AUTOINCREMENT .','
                . '`queue` ' . SmartlingEntityAbstract::DB_TYPE_STRING_STANDARD . ','
                . '`payload` ' . SmartlingEntityAbstract::DB_TYPE_STRING_TEXT . ','
                . '`payload_hash` ' . SmartlingEntityAbstract::DB_TYPE_HASH_MD5 . ','
                . ' PRIMARY KEY (`id`), INDEX(`queue`), UNIQUE(`queue`, `payload_hash`))',
                [
                    $tablePrefix,
                ]
            ),
        ];
    }
}