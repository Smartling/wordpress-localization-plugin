<?php

namespace Smartling\Tests\Traits;

/**
 * Class DateTimeBuilder
 * @package Smartling\Tests\Traits
 */
trait DateTimeBuilder
{

    /**
     * @param string $time
     * @param string $format
     * @param string $timezone
     *
     * @return \DateTime
     */
    private function mkDateTime($time, $format = 'Y-m-d H:i:s', $timezone = 'UTC')
    {
        return \DateTime::createFromFormat($format, $time, new \DateTimeZone($timezone));
    }
}