<?php

class AutoloaderTest extends PHPUnit_Framework_TestCase
{

    public function testSmartlingLoaderException()
    {
        $this->setExpectedException('SmartlingLoaderException');

        $str = 'Smartling';
        $classname = $str . str_shuffle($str);
        $obj = new $classname;
    }

    public function testClassMapAutoloader()
    {
        $classname = 'SmartlingLoaderException';

        $obj = new $classname;

        $this->assertInstanceOf($classname, $obj);
    }
}