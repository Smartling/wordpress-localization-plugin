<?php

namespace Smartling\WP;

/**
 * Interface WPHookInterface
 *
 * @package Smartling\WP
 */
interface WPHookInterface
{

    /**
     * Registers wp hook handlers. Invoked by wordpress.
     *
     * @return void
     */
    public function register();
}