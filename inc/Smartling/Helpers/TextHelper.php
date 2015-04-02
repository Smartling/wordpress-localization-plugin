<?php

namespace Smartling\Helpers;

/**
 * Class TextHelper
 *
 * @package Smartling\Helpers
 */
class TextHelper {

	/**
	 * mb safe wordwrap based on Drupal code
	 *
	 * @param string $string
	 * @param int    $width
	 * @param string $break
	 * @param bool   $cut
	 *
	 * @return string
	 */
	public static function mb_wordwrap ( $string, $width = 75, $break = "\n", $cut = false ) {
		$breakChars = array (
			"\n",
			"\r",
			"\t",
			"\0",
			"\x0B",
			',',
			'.',
			';',
			' ',
		);

		$cutPosition = 0;

		foreach ( $breakChars as $char ) {
			$pos = strpos( $string, $char, $width );

			if ( false !== $pos && $width >= $pos && $pos > $cutPosition ) {
				$cutPosition = $pos;
			}
		}

		return 0 === $cutPosition ? mb_substr( $string, 0, $width ) : mb_substr( $string, 0, $cutPosition - 1 );
	}
}