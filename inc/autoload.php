<?php

/**
 * Smartling settings
 */
defined('SM_DS') || define('SM_DS', DIRECTORY_SEPARATOR);

defined('SMARTLING_PLUGIN_DIR') ||
	define(
		'SMARTLING_PLUGIN_DIR',
		realpath(pathinfo(__FILE__, PATHINFO_DIRNAME) . SM_DS . '..' . SM_DS)
	);

/**
 * Composer autoload wrapper
 * Generates an \Exception if composer autoload file not found.
 */
$composerAutoLoadScriptFile
	= SMARTLING_PLUGIN_DIR . SM_DS . 'inc' . SM_DS . 'third-party' . SM_DS . 'autoload.php';

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
