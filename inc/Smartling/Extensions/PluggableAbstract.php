<?php

namespace Smartling\Extensions;

use Smartling\Helpers\PluginHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;

abstract class PluggableAbstract implements Pluggable {
    public function __construct(protected PluginHelper $pluginHelper, protected WordpressFunctionProxyHelper $wpProxy)
    {
    }

    public function getPluginSupportLevel(): string
    {
        $result = Pluggable::NOT_SUPPORTED;
        $plugins = $this->wpProxy->get_plugins();
        foreach ($this->getPluginPaths() as $path) {
            if (array_key_exists($path, $plugins) && $this->wpProxy->is_plugin_active($path)) {
                if ($this->pluginHelper->versionInRange($plugins[$path]['Version'] ?? 0, $this->getMinVersion(), $this->getMaxVersion())) {
                    return Pluggable::SUPPORTED;
                }
                $result = Pluggable::VERSION_NOT_SUPPORTED;
            }
        }

        return $result;
    }
}
