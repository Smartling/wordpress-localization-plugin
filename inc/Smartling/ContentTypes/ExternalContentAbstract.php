<?php

namespace Smartling\ContentTypes;

use Smartling\Helpers\PluginHelper;

abstract class ExternalContentAbstract implements ContentTypePluggableInterface {
    public function canHandle(PluginHelper $pluginHelper): bool
    {
        $activePlugins = wp_get_active_network_plugins();
        $plugins = get_plugins();
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

    public function getRelatedContent(string $contentType, int $id, array $targetBlogIds): array
    {
        return [];
    }
}
