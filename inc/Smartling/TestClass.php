<?php

namespace Smartling;

/**
 * Class TestClass
 *
 * @package Smartling
 *
 *
 * Just empty class to test PSR-0 autoloader
 */
class TestClass
{
    public function __construct($logger)
    {
        $logger->info(vsprintf('Autoloader successfully found and loaded \'%s\' class', [__CLASS__]));
    }
}