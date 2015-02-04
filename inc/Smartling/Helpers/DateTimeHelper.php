<?php

namespace Smartling\Helpers;

/**
 * Class DateTimeHelper
 *
 * @package Smartling\Helpers
 */
class DateTimeHelper {

	/**
	 * @var \DateTimeZone
	 */
	private static $tz_object;

	/**
	 * Default format string for DateTime
	 */
	const DATE_TIME_FORMAT_STANDARD = 'Y-m-d H:i:s';

	/**
	 * Sets default timezone for conversion operations
	 *
	 * @param null|string $timezone If [[null]] is set the timezone from php.ini is used. Default is "UTC'
	 */
	public static function setDefaultTimeZone ( $timezone = 'UTC' ) {
		if ( null === $timezone ) {
			$timezone = date_default_timezone_get();
		}
		self::$tz_object = new \DateTimeZone( $timezone );
	}

	/**
	 * @return \DateTimeZone
	 */
	public static function getDefaultTimezone () {
		if ( ! ( self::$tz_object instanceof \DateTimeZone ) ) {
			self::setDefaultTimeZone();

		}

		return self::$tz_object;
	}

	/**
	 * Converts formatted string to \DateTime object
	 *
	 * @param string $dateTime
	 * @param string $format
	 *
	 * @return false|\DateTime
	 */
	public static function stringToDateTime ( $dateTime, $format = self::DATE_TIME_FORMAT_STANDARD ) {
		return \DateTime::createFromFormat( $format, $dateTime, self::getDefaultTimezone() );
	}

	/**
	 * Converts \DateTime object to formatted string
	 *
	 * @param \DateTime $dateTime
	 * @param string    $format
	 *
	 * @return string
	 */
	public static function dateTimeToString ( \DateTime $dateTime, $format = self::DATE_TIME_FORMAT_STANDARD ) {
		return $dateTime->format( $format );
	}

	/**
	 * Converts unix timestamp to \DateTime object
	 *
	 * @param int $dateTime
	 *
	 * @return false|\DateTime
	 */
	public static function timestampToDateTime ( $dateTime ) {
		$dateTimeObject = new \DateTime( 'now', self::getDefaultTimezone() );

		return $dateTimeObject->setTimestamp( $dateTime );
	}

	/**
	 * Converts \DateTime object to unix timestamp
	 *
	 * @param \DateTime $dateTime
	 *
	 * @return int
	 */
	public static function dateTimeToTimestamp ( \DateTime $dateTime ) {
		return $dateTime->getTimestamp();
	}
}