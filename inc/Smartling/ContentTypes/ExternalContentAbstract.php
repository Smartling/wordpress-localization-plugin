<?php

namespace Smartling\ContentTypes;

use Smartling\Helpers\PluginHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;

abstract class ExternalContentAbstract implements ContentTypePluggableInterface {
    public function canHandle(PluginHelper $pluginHelper, int $contentId, WordpressFunctionProxyHelper $wpProxy): bool
    {
        $activePlugins = $wpProxy->wp_get_active_network_plugins();
        $plugins = $wpProxy->get_plugins();
        foreach ($activePlugins as $plugin) {
            $parts = array_reverse(explode('/', $plugin));
            if (count($parts) < 2) {
                continue;
            }
            $path = implode('/', [$parts[1], $parts[0]]);
            if ($path === $this->getPluginPath()) {
                if (!array_key_exists($path, $plugins)) {
                    return false;
                }

                return $pluginHelper->versionInRange($plugins[$path]['Version'] ?? '0', $this->getMinVersion(), $this->getMaxVersion());
            }
        }

        return false;
    }

    public function getRelatedContent(string $contentType, int $id): array
    {
        return [];
    }
}
