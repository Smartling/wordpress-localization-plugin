<?php

namespace Smartling\Helpers;

/**
 * Class SimpleStorageHelper
 *
 * @package Smartling\Helpers
 */
class SimpleStorageHelper {

	/**
	 * @param string $storageKey
	 * @param mixed  $defaultValue
	 *
	 * @return mixed
	 */
	public static function get ( $storageKey, $defaultValue = null ) {
		return get_site_option( $storageKey, $defaultValue );
	}

	/**
	 * @param string $storageKey
	 * @param mixed  $value
	 */
	public static function set ( $storageKey, $value ) {
		if ( false === self::get( $storageKey, false ) ) {
			add_site_option( $storageKey, $value );
		} else {
			update_site_option( $storageKey, $value );
		}
	}
}