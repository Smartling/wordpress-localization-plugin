<?php

namespace Smartling\Helpers;

class PluginHelper
{
    private WordpressFunctionProxyHelper $wpProxy;

    public function __construct(WordpressFunctionProxyHelper $wpProxy)
    {
        $this->wpProxy = $wpProxy;
    }

    public function pluginVersionInRange(string $pluginPath, string $minVersion, string $maxVersion): bool
    {
        $plugins = $this->wpProxy->get_plugins();
        if (array_key_exists($pluginPath, $plugins)) {
            return $this->versionInRange($plugins[$pluginPath]['Version'] ?? '0', $minVersion, $maxVersion);
        }

        return false;
    }

    public function versionInRange(string $version, string $minVersion, string $maxVersion): bool
    {
        $maxVersionParts = explode('.', $maxVersion);
        $versionParts = explode('.', $version);
        foreach ($maxVersionParts as $index => $part) {
            if (!array_key_exists($index, $versionParts)) {
                return false; // misconfiguration
            }
            if ($versionParts[$index] > $part) {
                return false; // not supported
            }
        }

        return version_compare($version, $minVersion, '>=');
    }
}
