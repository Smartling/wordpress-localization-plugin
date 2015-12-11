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

	/**
	 * @param string $pattern
	 * @param string $borders
	 * @param string $modifiers
	 *
	 * @return string
	 */
	public static function buildPattern ( $pattern, $borders = '/', $modifiers = 'ius' ) {
		return vsprintf(
			'%s%s%s%s',
			[
				$borders,
				$pattern,
				$borders,
				$modifiers,
			]
		);
	}
}