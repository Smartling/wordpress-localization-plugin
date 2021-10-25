<?php
/**
 * Smartling settings
 */
defined( 'SMARTLING_PLUGIN_DIR' ) ||
define(
'SMARTLING_PLUGIN_DIR',
realpath( pathinfo( __FILE__, PATHINFO_DIRNAME ) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR )
);

/**
 * Composer autoload wrapper
 * Generates an \Exception if composer autoload file not found.
 */
$composerAutoLoadScriptFile
	= SMARTLING_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'autoload.php';

if ( file_exists( $composerAutoLoadScriptFile ) && is_readable( $composerAutoLoadScriptFile ) ) {
	require_once $composerAutoLoadScriptFile;
} else {
	throw new \Exception(
		vsprintf(
			"Expected autoloader script not found at '%s'",
			array ( $composerAutoLoadScriptFile )
		), 1 );
}
