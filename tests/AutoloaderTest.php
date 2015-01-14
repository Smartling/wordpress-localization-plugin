<?php

class AutoloaderTest extends PHPUnit_Framework_TestCase
{
    public function testClassMapAutoloader()
    {
        $classname = '\Smartling\TestClass';

        $obj = new $classname;

        $this->assertInstanceOf($classname, $obj);
    }
}