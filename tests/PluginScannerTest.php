<?php

use Smartling\Bootstrap;

/**
 * Class PluginScannerTest
 */
class PluginScannerTest extends PHPUnit_Framework_TestCase {

	/**
	 * @expectedException Smartling\Exception\MultilingualPluginNotFoundException
	 */
	public function testNoPluginFoundException () {
		$bootstrap = new Bootstrap();

		$bootstrap->detectMultilangPlugins();
	}

}