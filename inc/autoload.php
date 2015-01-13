<?php
/**
 * Paths definitions
 */
defined('classlib')
	|| define('classlib', '.');

defined('absClassDir')
	|| define('absClassDir', realpath(pathinfo(__FILE__, PATHINFO_DIRNAME) . DIRECTORY_SEPARATOR . classlib));

/**
 * Settings definitions
 */
defined('smartlingClassMapFile')
	|| define('smartlingClassMapFile', 'classmap.json');

/**
 * Loads classes based on classmap json file or throws an SmartlingLoaderException
 *
 * @param string $className
 *
 * @throws Exception if classmap file not found
 * @throws SmartlingLoaderException if map doesn't contain info about a class
 */
function classMapLoader ($className)
{
	if (!isset($GLOBALS['smartlingClassMap'])) {
		$possibleFileName = absClassDir . DIRECTORY_SEPARATOR . smartlingClassMapFile;

		if (!is_readable($possibleFileName) || !file_exists($possibleFileName)) {
			throw new Exception(vsprintf('No classmap file found at "%s"', array($possibleFileName)));
		} else {
			$GLOBALS['smartlingClassMap'] = (array) json_decode(file_get_contents($possibleFileName));
		}
	}

	if (array_key_exists($className, $GLOBALS['smartlingClassMap'])) {
		/** @noinspection PhpIncludeInspection */
		require_once absClassDir . DIRECTORY_SEPARATOR . $GLOBALS['smartlingClassMap'][$className];
	} else {
		$pos = strpos($className, 'Smartling');
		if (false !== $pos && 0 == $pos) {
			throw new SmartlingLoaderException(vsprintf("Cannot load class %s", array($className)));
		}
	}
}

defined('smartling_loader')
	|| define('smartling_loader', 'classMapLoader');

spl_autoload_register(smartling_loader);