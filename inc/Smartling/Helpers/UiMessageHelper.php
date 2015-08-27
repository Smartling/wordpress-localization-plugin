<?php

namespace Smartling\Helpers;

/**
 * Class UiMessageHelper
 *
 * @package Smartling\Helpers
 */
class UiMessageHelper {
	public static function displayMessages () {
		$type     = 'error';
		$messages = DiagnosticsHelper::getMessages();
		if ( 0 < count( $messages ) ) {
			$msg = '';
			foreach ( $messages as $message ) {
				$msg .= vsprintf( '<div class="%s"><p>%s</p></div>', [ $type, $message ] );
			}
			echo $msg;
			DiagnosticsHelper::reset();
		}
	}
}