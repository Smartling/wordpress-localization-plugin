<?php

/**
 * Composer autoload wrapper
 * Generates an \Exception if composer autoload file not found.
 */

$composerAutoLoadScriptFile
	= pathinfo(__FILE__, PATHINFO_DIRNAME) . DIRECTORY_SEPARATOR . 'third-party' . DIRECTORY_SEPARATOR . 'autoload.php';

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
