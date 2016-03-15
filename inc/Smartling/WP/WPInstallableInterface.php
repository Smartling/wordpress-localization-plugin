<?php

namespace Smartling\WP;

/**
 * Interface WPInstallableInterface
 * @package Smartling\WP
 */
interface WPInstallableInterface
{
    /**
     * Is triggered on plugin uninstall
     * @return void
     */
    public function uninstall();

    /**
     * Is triggered on plugin activation
     * @return void
     */
    public function activate();

    /**
     * Is triggered on plugin deactivation
     * @return void
     */
    public function deactivate();
}