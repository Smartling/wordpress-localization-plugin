<?php
/**
 * Smartling settings
 */
defined('SMARTLING_PLUGIN_DIR') ||
	define(
		'SMARTLING_PLUGIN_DIR',
		realpath(pathinfo(__FILE__, PATHINFO_DIRNAME) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR)
	);

/**
 * Composer autoload wrapper
 * Generates an \Exception if composer autoload file not found.
 */
$composerAutoLoadScriptFile
	= SMARTLING_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'third-party' . DIRECTORY_SEPARATOR . 'autoload.php';

if (file_exists($composerAutoLoadScriptFile) && is_readable($composerAutoLoadScriptFile)) {
	/** @noinspection PhpIncludeInspection */
	require_once $composerAutoLoadScriptFile;
} else {
	throw new \Exception(
		vsprintf(
			"Expected autoloader script not found at '%s'",
			array($composerAutoLoadScriptFile)
		), 1);
}

/**
 * To switch classes that are using in real Wordpress or in unit tests
 */
defined('SMARTLING_CLI_EXECUTION')
	|| define('SMARTLING_CLI_EXECUTION', !defined('ABSPATH'));

/**
 * Debug is always on for UnitTests
 */
defined('SMARTLING_DEBUG')
	|| define('SMARTLING_DEBUG', SMARTLING_CLI_EXECUTION);


if (defined('SMARTLING_DEBUG') && SMARTLING_DEBUG === true) {
	error_reporting(E_ALL ^ E_STRICT);
	ini_set('display_errors', 'on');
}


