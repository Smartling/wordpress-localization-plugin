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
		if ( is_string( $message ) ) {
			self::$messages[] = $message;
			if ( true === $blockPlugin ) {
				self::$pluginBlocked = true;
			}
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

	public static function populateErrorsToWordpress () {
		$messages = self::getMessages();

		if ( 0 < count( $messages ) ) {
			global $error;

			if ( ! ( $error instanceof \WP_Error ) ) {
				$error = new \WP_Error();
				foreach ( $messages as $message ) {
					$error->add( 'smartling', $message );
				}
			}
		}

	}

	public static function reset () {
		self::$messages = array ();
	}
}