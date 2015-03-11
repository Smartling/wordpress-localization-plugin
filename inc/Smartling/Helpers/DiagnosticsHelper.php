<?php

namespace Smartling\Helpers;

/**
 * Class DiagnosticsHelper
 *
 * @package Smartling\Helpers
 */
class DiagnosticsHelper {

	/**
	 * Flag that indicates that plugin functionality is blocked
	 *
	 * @var bool
	 */
	private static $pluginBlocked = false;

	/**
	 * Error messages
	 *
	 * @var array
	 */
	private static $messages = array ();

	/**
	 * @param string $message
	 * @param bool   $blockPlugin
	 */
	public static function addDiagnosticsMessage ( $message, $blockPlugin = false ) {
		self::setBlockStatus( $blockPlugin );
		if ( is_string( $message ) ) {
			self::$messages[] = $message;
		}
	}

	/**
	 * @param $blocked
	 */
	private static function setBlockStatus ( $blocked ) {
		if ( is_bool( $blocked ) && false === self::$pluginBlocked ) {
			self::$pluginBlocked = $blocked;
		}
	}

	/**
	 * Returns the block flag value
	 *
	 * @return bool
	 */
	public static function isBlocked () {
		return (bool) self::$pluginBlocked;
	}

	/**
	 * Returns error messages array
	 *
	 * @return array
	 */
	public static function getMessages () {
		return self::$messages;
	}
}