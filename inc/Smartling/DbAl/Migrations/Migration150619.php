<?php
namespace Smartling\DbAl\Migrations;

use Smartling\Base\SmartlingEntityAbstract;

/**
 * Class Migration150619
 *
 * @package Smartling\DbAl\Migrations
 *
 * Adds is_locked column to submission_entity
 */
class Migration150619 implements SmartlingDbMigrationInterface {
	public function getVersion () {
		return 150619;
	}

	public function getQueries ( $tablePrefix = '' ) {
		return array (
			vsprintf(
				'ALTER TABLE `%ssmartling_submissions` ADD COLUMN `is_locked` %s %s',
				array (
					$tablePrefix,
					SmartlingEntityAbstract::DB_TYPE_UINT_SWITCH,
					SmartlingEntityAbstract::DB_TYPE_DEFAULT_ZERO
				)
			),
		);
	}
}