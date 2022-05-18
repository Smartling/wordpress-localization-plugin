<?php

namespace Smartling\Helpers;

use Smartling\ContentTypes\ContentTypePluggableInterface;

class PluginHelper
{
    public function canHandleExternalContent(ContentTypePluggableInterface $handler): bool
    {
        $activePlugins = wp_get_active_network_plugins();
        $plugins = get_plugins();
        foreach ($activePlugins as $plugin) {
            $parts = array_reverse(explode('/', $plugin));
            if (count($parts) < 2) {
                continue;
            }
            $path = implode('/', [$parts[1], $parts[0]]);
            if ($path === $handler->getPluginPath()) {
                if (!array_key_exists($path, $plugins)) {
                    return false;
                }
                return $this->versionInRange($plugins[$path]['Version'] ?? '0', $handler->getMinVersion(), $handler->getMaxVersion());
            }
        }
        return false;
    }

    public function versionInRange(string $version, string $minVersion, string $maxVersion): bool
    {
        $maxVersionParts = explode('.', $maxVersion);
        $versionParts = explode('.', $version);
        $potentiallyNotSupported = false;
        foreach ($maxVersionParts as $index => $part) {
            if (!array_key_exists($index, $versionParts)) {
                return false; // misconfiguration
            }
            if ($versionParts[$index] > $part && $potentiallyNotSupported) {
                return false; // not supported
            }

            $potentiallyNotSupported = $versionParts[$index] === $part;
        }

        return version_compare($version, $minVersion, '>=');
    }
}
