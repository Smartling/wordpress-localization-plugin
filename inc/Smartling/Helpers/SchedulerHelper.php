<?php

namespace Smartling\Helpers;

/**
 * Class SchedulerHelper
 *
 * @package Smartling\Helpers
 */
class SchedulerHelper {
	public function extendWpCron ( $schedules ) {
		$intervals = array(
			'2m' => array(
				'interval' => 120,
				'display' => __('Every 2 minutes')
			),
			'5m' => array(
				'interval' => 300,
				'display' => __('Every 5 minutes')
			),
			'10m' => array(
				'interval' => 600,
				'display' => __('Every 10 minutes')
			),
			'15m' => array(
				'interval' => 900,
				'display' => __('Every 15 minutes')
			),
		);

		return array_merge($schedules, $intervals);
	}
}