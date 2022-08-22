<?php

namespace Smartling\Helpers;

class PluginHelper
{
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
