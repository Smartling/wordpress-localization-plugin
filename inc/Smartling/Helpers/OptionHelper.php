<?php

namespace Smartling\Helpers;

/**
 * Class OptionHelper
 *
 * @package Smartling\Helpers
 */
class OptionHelper {

	private static $internalDefault = 'SmartlingDefaultValueMarker';

	public static function get ( $key, $default = false ) {
		$result = get_option( $key, self::$internalDefault );
		if ( $result === self::$internalDefault ) {
			$result = $default;
		}

		return $result;
	}

	public static function set ( $key, $value ) {
		self::$internalDefault === self::get( $key, self::$internalDefault )
			? add_option( $key, $value )
			: update_option( $key, $value );
	}
}