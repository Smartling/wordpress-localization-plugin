<?php

namespace Smartling\Helpers;

/**
 * Class StringHelper
 *
 * @package Smartling\Helpers
 */
class StringHelper {

	/**
	 * Empty string constant
	 */
	const EMPTY_STRING = '';

	/**
	 * Checks if string is false null of empty
	 *
	 * @param $string
	 *
	 * @return bool
	 */
	public static function isNullOrEmpty ( $string ) {
		return in_array( $string, [ false, null, self::EMPTY_STRING ] );
	}
}