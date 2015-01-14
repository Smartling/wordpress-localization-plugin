<?php

namespace Smartling;

/**
 * Class TestClass
 * @package Smartling
 *
 *
 * Just empty class to test PSR-0 autoloader
 */
class TestClass {
    public function __construct() {
        Bootstrap::getContainer()->get('logger')->addInfo('Running unit test of autoloader.');
    }
}