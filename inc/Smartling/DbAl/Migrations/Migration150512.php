<?php
namespace Smartling\DbAl\Migrations;

use Smartling\Base\SmartlingEntityAbstract;

/**
 * Class Migration150512
 *
 * @package Smartling\DbAl\Migrations
 *
 * Adds default values to
 *          submission_entity.approved_string_count  = 0
 *          submission_entity.completed_string_count = 0
 *          submission_entity.word_count             = 0
 */
class Migration150512 implements SmartlingDbMigrationInterface {
	public function getVersion () {
		return 150512;
	}

	public function getQueries ( $tablePrefix = '' ) {
		return array (
			vsprintf(
				'ALTER TABLE `%ssmartling_submissions` CHANGE COLUMN `approved_string_count` `approved_string_count` %s %s',
				array (
					$tablePrefix,
					SmartlingEntityAbstract::DB_TYPE_U_BIGINT,
					SmartlingEntityAbstract::DB_TYPE_DEFAULT_ZERO
				)
			),
			vsprintf(
				'ALTER TABLE `%ssmartling_submissions` CHANGE COLUMN `completed_string_count` `completed_string_count` %s %s',
				array (
					$tablePrefix,
					SmartlingEntityAbstract::DB_TYPE_U_BIGINT,
					SmartlingEntityAbstract::DB_TYPE_DEFAULT_ZERO
				)
			),
			vsprintf(
				'ALTER TABLE `%ssmartling_submissions` CHANGE COLUMN `word_count` `word_count` %s %s',
				array (
					$tablePrefix,
					SmartlingEntityAbstract::DB_TYPE_U_BIGINT,
					SmartlingEntityAbstract::DB_TYPE_DEFAULT_ZERO
				)
			)
		);
	}
}