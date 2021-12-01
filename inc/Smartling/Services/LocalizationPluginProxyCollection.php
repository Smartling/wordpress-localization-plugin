<?php

namespace Smartling\Services;

use Smartling\DbAl\DummyLocalizationPlugin;
use Smartling\DbAl\LocalizationPluginProxyInterface;

class LocalizationPluginProxyCollection
{
    /**
     * @var LocalizationPluginProxyInterface[]
     */
    private array $collection = [];

    public function addConnector(LocalizationPluginProxyInterface $connector): void
    {
        $this->collection[] = $connector;
    }

    /**
     * @return LocalizationPluginProxyInterface[]
     */
    public function getCollection(): array
    {
        return $this->collection;
    }

    public function getActivePlugin(): LocalizationPluginProxyInterface
    {
        foreach ($this->collection as $plugin) {
            if ($plugin->isActive()) {
                return $plugin;
            }
        }
        return new DummyLocalizationPlugin();
    }
}
