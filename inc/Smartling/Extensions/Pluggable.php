<?php

namespace Smartling\Extensions;

use JetBrains\PhpStorm\ExpectedValues;

interface Pluggable {
    public const NOT_SUPPORTED = 'not_supported';
    public const SUPPORTED = 'supported';
    public const VERSION_NOT_SUPPORTED = 'version_not_supported';

    public function getMaxVersion(): string;

    public function getMinVersion(): string;

    /**
     * Key under which the handler's data is stored in submissions, must stay stable across versions
     */
    public function getPluginId(): string;

    /**
     * Name to use in logs, may differ from the plugin id when handlers share it
     */
    public function getLogName(): string;

    /**
     * @return array with possible paths to the plugin file relative to the plugins directory.
     * Most plugins have a single possible path, some have variations.
     */
    public function getPluginPaths(): array;

    #[ExpectedValues(valuesFromClass: self::class)]
    public function getPluginSupportLevel(): string;
}
