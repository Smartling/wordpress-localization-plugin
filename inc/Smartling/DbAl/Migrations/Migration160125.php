<?php
namespace Smartling\DbAl\Migrations;

use Smartling\Bootstrap;

/**
 * Class Migration160125
 *
 * @package Smartling\DbAl\Migrations
 *
 */
class Migration160125 implements SmartlingDbMigrationInterface
{
    public function getVersion()
    {
        return 160125;
    }

    public function getQueries($tablePrefix = '')
    {
        $defaultFilter = Bootstrap::getContainer()->getParameter('field.processor.default');

        return [
            vsprintf(
                'ALTER TABLE `%ssmartling_configuration_profiles` ADD COLUMN `filter_skip` TEXT NULL',
                [
                    $tablePrefix,
                ]
            ),
            vsprintf(
                'ALTER TABLE `%ssmartling_configuration_profiles` ADD COLUMN `filter_copy_by_field_name` TEXT NULL',
                [
                    $tablePrefix,
                ]
            ),
            vsprintf(
                'ALTER TABLE `%ssmartling_configuration_profiles` ADD COLUMN `filter_copy_by_field_value_regex` TEXT NULL',
                [
                    $tablePrefix,
                ]
            ),
            vsprintf(
                'ALTER TABLE `%ssmartling_configuration_profiles` ADD COLUMN `filter_flag_seo` TEXT NULL',
                [
                    $tablePrefix,
                ]
            ),
            vsprintf(
                'UPDATE `%ssmartling_configuration_profiles` SET `filter_flag_seo` = \'%s\' WHERE `filter_flag_seo` IS NULL',
                [
                    $tablePrefix,
                    implode(PHP_EOL, $defaultFilter['key']['seo']),
                ]
            ),
            vsprintf(
                'UPDATE `%ssmartling_configuration_profiles` SET `filter_copy_by_field_value_regex` = \'%s\' WHERE `filter_copy_by_field_value_regex` IS NULL',
                [
                    $tablePrefix,
                    implode(PHP_EOL, $defaultFilter['copy']['regexp']),
                ]
            ),
            vsprintf(
                'UPDATE `%ssmartling_configuration_profiles` SET `filter_copy_by_field_name` = \'%s\' WHERE `filter_copy_by_field_name` IS NULL',
                [
                    $tablePrefix,
                    implode(PHP_EOL, $defaultFilter['copy']['name']),
                ]
            ),
            vsprintf(
                'UPDATE `%ssmartling_configuration_profiles` SET `filter_skip` = \'%s\' WHERE `filter_skip` IS NULL',
                [
                    $tablePrefix,
                    implode(PHP_EOL, $defaultFilter['ignore']),
                ]
            ),
        ];
    }
}