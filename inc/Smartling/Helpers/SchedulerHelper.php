<?php

namespace Smartling\Helpers;

/**
 * Class SchedulerHelper
 *
 * @package Smartling\Helpers
 */
class SchedulerHelper {
	public function extendWpCron ( $schedules ) {
		$intervals = [
			'2m'  => [
				'interval' => 120,
				'display'  => __( 'Every 2 minutes' ),
			],
			'5m'  => [
				'interval' => 300,
				'display'  => __( 'Every 5 minutes' ),
			],
			'10m' => [
				'interval' => 600,
				'display'  => __( 'Every 10 minutes' ),
			],
			'15m' => [
				'interval' => 900,
				'display'  => __( 'Every 15 minutes' ),
			],
		];

		return array_merge( $schedules, $intervals );
	}
}