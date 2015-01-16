<?php

use Smartling\Bootstrap;

class AutoloaderTest extends PHPUnit_Framework_TestCase
{
    private $logger = null;

    /**
     * @inheritdoc
     */
    public function __construct($name = null, array $data = array(), $dataName = '')
    {
        $this->logger = Bootstrap::getContainer()->get('logger');

        $this->logger->info(vsprintf('Testing Autoloader in \'%s\' class', array(__CLASS__)));

        parent::__construct($name, $data, $dataName);
    }

    public function testClassMapAutoloader()
    {
        $classname = '\Smartling\TestClass';

        $obj = new $classname($this->logger);

        $this->assertInstanceOf($classname, $obj);
    }
}