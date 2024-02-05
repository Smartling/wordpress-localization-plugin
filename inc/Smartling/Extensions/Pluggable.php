<?php

namespace Smartling\Extensions;

use JetBrains\PhpStorm\ExpectedValues;

interface Pluggable {
    public const NOT_SUPPORTED = 'not_supported';
    public const SUPPORTED = 'supported';
    public const VERSION_NOT_SUPPORTED = 'version_not_supported';

    public function getMaxVersion(): string;

    public function getMinVersion(): string;

    public function getPluginId(): string;

    /**
     * @return array with possible paths to the plugin file relative to the plugins directory.
     * Most plugins have a single possible path, some have variations.
     */
    public function getPluginPaths(): array;

    #[ExpectedValues(valuesFromClass: self::class)]
    public function getPluginSupportLevel(): string;
}
