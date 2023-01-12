<?php

namespace Smartling\WP;

interface WPInstallableInterface
{
    /**
     * Is triggered on plugin uninstall
     */
    public function uninstall(): void;

    /**
     * Is triggered on plugin activation
     */
    public function activate(): void;

    /**
     * Is triggered on plugin deactivation
     */
    public function deactivate(): void;
}