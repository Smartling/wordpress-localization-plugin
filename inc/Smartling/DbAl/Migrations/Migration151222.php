<?php

namespace Smartling\DbAl\Migrations;

use Smartling\Base\SmartlingEntityAbstract;

/**
 * Class Migration151222
 *
 * @package Smartling\DbAl\Migrations
 */
class Migration151222 implements SmartlingDbMigrationInterface
{
    public function getVersion()
    {
        return 151222;
    }

    public function getQueries($tablePrefix = '')
    {
        return [
            vsprintf(
                'ALTER TABLE `%ssmartling_configuration_profiles` CHANGE COLUMN `api_key` `user_identifier` %s',
                [
                    $tablePrefix,
                    SmartlingEntityAbstract::DB_TYPE_STRING_STANDARD,
                ]
            ),
            vsprintf(
                'ALTER TABLE `%ssmartling_configuration_profiles` ADD COLUMN `secret_key` %s AFTER `user_identifier`',
                [
                    $tablePrefix,
                    SmartlingEntityAbstract::DB_TYPE_STRING_STANDARD,
                ]
            ),
        ];
    }
}