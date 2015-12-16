<?php

namespace Smartling\Helpers;

/**
 * Class FileHelper
 *
 * @package Smartling\Helpers
 */
class FileHelper {

	/**
	 * Provides simple checks for the file.
	 * @param $filePath
	 *
	 * @return bool
	 */
	public static function testFile ( $filePath ) {
		return (
			file_exists( $filePath )
			&& is_file( $filePath )
			&& is_readable( $filePath )
		);
	}
}