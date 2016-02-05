<?php

namespace Smartling\Extensions;

/**
 * Interface ExtensionInterface
 *
 * @package Smartling\Extensions
 */
interface ExtensionInterface
{

    /**
     * Gets the unique extension identifier
     *
     * @return string
     */
    public function getName();

    /**
     * Runs the initialization of extension
     *
     * @return void
     */
    public function register();
}