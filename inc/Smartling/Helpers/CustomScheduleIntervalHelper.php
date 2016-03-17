<?php

namespace Smartling\Helpers;

use Smartling\WP\WPHookInterface;

/**
 * Class CustomScheduleIntervalHelper
 * @package Smartling\Helpers
 */
class CustomScheduleIntervalHelper implements WPHookInterface
{

    private static $intervals = [];

    /**
     * @inheritdoc
     */
    public function register()
    {
        add_filter('cron_schedules', [$this, 'updateSchedules']);
    }

    /**
     * @param array $schedules
     *
     * @return array
     */
    public function updateSchedules($schedules)
    {
        return array_merge($schedules, self::$intervals);
    }

    /**
     * @param string $intervalId
     * @param int    $intervalLength
     * @param string $intervalDisplay
     */
    public function registerInterval($intervalId, $intervalLength, $intervalDisplay)
    {
        self::$intervals[$intervalId] = [
            'interval' => $intervalLength,
            'display'  => $intervalDisplay,
        ];
    }

}