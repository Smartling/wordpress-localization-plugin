<?php

use Smartling\Bootstrap;

class MultilangPluginScannerTest extends PHPUnit_Framework_TestCase
{

    /**
     * @expectedException Smartling\Exception\MultilingualPluginNotFoundException
     */
    public function testClassMapAutoloader()
    {
        $bootstrap = new Bootstrap();

        $bootstrap->detectMultilangPlugins();
    }

}