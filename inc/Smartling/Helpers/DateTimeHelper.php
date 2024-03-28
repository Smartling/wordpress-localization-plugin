<?php

namespace Smartling\Helpers;

use DateTime;
use DateTimeZone;


/**
 * Class DateTimeHelper
 * @package Smartling\Helpers
 */
class DateTimeHelper
{

    /**
     * UTC Timezone
     */
    const TIMEZONE_UTC = 'UTC';

    /**
     * @var DateTimeZone
     */
    private static $tz_object;

    /**
     * @var string
     */
    private static $wp_date_format;

    /**
     * @var string
     */
    private static $wp_time_format;

    /**
     * @var null|DateTimeZone
     */
    private static $wp_local_timezone;

    /**
     * Default format string for DateTime
     */
    const DATE_TIME_FORMAT_STANDARD = 'Y-m-d H:i:s';

    const DATE_TIME_FORMAT_JOB = 'Y-m-d H:i';

    /**
     * Sets default timezone for conversion operations
     *
     * @param null|string $timezone If [[null]] is set the timezone from php.ini is used. Default is "UTC'
     */
    public static function setDefaultTimeZone($timezone = self::TIMEZONE_UTC)
    {
        if (null === $timezone) {
            $timezone = date_default_timezone_get();
        }
        static::$tz_object = new DateTimeZone($timezone);
    }

    /**
     * @return \DateTimeZone
     */
    public static function getDefaultTimezone()
    {
        if (!(static::$tz_object instanceof DateTimeZone)) {
            static::setDefaultTimeZone();

        }

        return static::$tz_object;
    }

    /**
     * Converts formatted string to \DateTime object
     *
     * @param string $dateTime
     * @param string $format
     *
     * @return false|\DateTime
     */
    public static function stringToDateTime($dateTime, $format = self::DATE_TIME_FORMAT_STANDARD)
    {
        return \DateTime::createFromFormat($format, $dateTime, static::getDefaultTimezone());
    }

    /**
     * Converts \DateTime object to formatted string
     *
     * @param DateTime $dateTime
     * @param string   $format
     *
     * @return string
     */
    public static function dateTimeToString(DateTime $dateTime, $format = self::DATE_TIME_FORMAT_STANDARD)
    {
        return $dateTime->format($format);
    }

    /**
     * Converts unix timestamp to \DateTime object
     *
     * @param int $dateTime
     *
     * @return false|DateTime
     */
    public static function timestampToDateTime($dateTime)
    {
        $dateTimeObject = new DateTime('now', static::getDefaultTimezone());

        return $dateTimeObject->setTimestamp($dateTime);
    }

    /**
     * Converts DateTime object to unix timestamp
     *
     * @param DateTime $dateTime
     *
     * @return int
     */
    public static function dateTimeToTimestamp(DateTime $dateTime)
    {
        return $dateTime->getTimestamp();
    }

    /**
     * Returns current date and time as a string like '2014-18-14 22:18:63' in UTC timezone
     * @return string
     */
    public static function nowAsString()
    {
        return static::dateTimeToString(new DateTime('now', static::getDefaultTimezone()));
    }

    public static function getWordpressDateFormat(): string
    {
        if (null === static::$wp_date_format) {
            static::$wp_date_format = get_option('date_format');
        }

        return static::$wp_date_format;
    }

    /**
     * @return string
     */
    public static function getWordpressTimeFormat()
    {
        if (null === static::$wp_time_format) {
            static::$wp_time_format = get_option('time_format');
        }

        return static::$wp_time_format;
    }

    /**
     * @return DateTimeZone
     */
    public static function getWordpressTimeZone()
    {
        if (null === static::$wp_local_timezone) {
            $tz = get_option('timezone_string', static::TIMEZONE_UTC);
            $tz = empty($tz) ? static::TIMEZONE_UTC : $tz;
            static::$wp_local_timezone = new DateTimeZone($tz);
        }

        return static::$wp_local_timezone;
    }

    /**
     * @return string
     */
    public static function getWordpressDateTimeFormat()
    {
        return static::getWordpressDateFormat() . ' ' . static::getWordpressTimeFormat();
    }

    /**
     * @param DateTime $dateTime
     *
     * @return string
     */
    public static function toWordpressLocalDateTime(DateTime $dateTime)
    {
        $o = clone $dateTime;

        $o->setTimezone(static::getWordpressTimeZone());

        return $o->format(static::getWordpressDateTimeFormat());
    }
}