<?php

namespace Smartling\WP;

interface WPHookInterface
{
    /**
     * Registers wp hook handlers. Invoked by wordpress.
     */
    public function register(): void;
}
