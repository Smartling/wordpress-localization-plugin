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

defined('SMARTLING_LOGLEVEL_DEBUG')
	|| define('SMARTLING_LOGLEVEL_DEBUG',		100);
defined('SMARTLING_LOGLEVEL_INFO')
	|| define('SMARTLING_LOGLEVEL_INFO',		200);
defined('SMARTLING_LOGLEVEL_NOTICE')
	|| define('SMARTLING_LOGLEVEL_NOTICE',		250);
defined('SMARTLING_LOGLEVEL_WARNING')
	|| define('SMARTLING_LOGLEVEL_WARNING',		300);
defined('SMARTLING_LOGLEVEL_ERROR')
	|| define('SMARTLING_LOGLEVEL_ERROR',		400);
defined('SMARTLING_LOGLEVEL_CRITICAL')
	|| define('SMARTLING_LOGLEVEL_CRITICAL',	500);
defined('SMARTLING_LOGLEVEL_ALERT')
	|| define('SMARTLING_LOGLEVEL_ALERT',		550);
defined('SMARTLING_LOGLEVEL_EMERGENCY')
	|| define('SMARTLING_LOGLEVEL_EMERGENCY',	600);

defined('SMARTLING_DEFAULT_LOGLEVEL') || define('SMARTLING_DEFAULT_LOGLEVEL', SMARTLING_LOGLEVEL_DEBUG);

defined('SMARTLING_DEFAULT_LOG_CHANNEL') || define('SMARTLING_DEFAULT_LOG_CHANNEL', 'default');

defined('SMARTLING_DEFAULT_LOGFILE') || define('SMARTLING_DEFAULT_LOGFILE', SMARTLING_PLUGIN_DIR . SM_DS . 'logfile.log');

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

/**
 * Bootstrap
 */
\Smartling\Bootstrap::__bootstrap();