<?php

namespace Smartling;

use Smartling\Exception\SmartlingConfigException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class DI
{
    private $containerInstance;

    /**
     * Extracts mixed from container
     *
     * @param string $id
     * @param bool   $isParam
     *
     * @return mixed
     */
    public function fromContainer($id, $isParam = false)
    {
        $container = $this->getContainer();
        $content = null;

        if ($isParam) {
            $content = $container->getParameter($id);
        } else {
            $content = $container->get($id);
        }

        return $content;
    }

    /**
     * @return ContainerBuilder
     * @throws SmartlingConfigException
     */
    public function getContainer()
    {
        if (null === $this->containerInstance) {
            $this->initContainer();
        }

        return $this->containerInstance;
    }

    /**
     * Initializes DI Container from YAML config file
     * @throws SmartlingConfigException
     */
    private function initContainer()
    {
        $container = new ContainerBuilder();

        // region set core parameters
        $container->setParameter('plugin.dir', SMARTLING_PLUGIN_DIR);
        $container->setParameter('plugin.upload', SMARTLING_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'upload');

        $pluginUrl = '';

        if (function_exists('plugin_dir_url')) {
            $pluginUrl = plugin_dir_url(SMARTLING_PLUGIN_DIR . DIRECTORY_SEPARATOR . '..');
        }

        $container->setParameter('plugin.url', $pluginUrl);
        // endregion

        $configDir = [SMARTLING_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'config'];

        $fileLocator = new FileLocator($configDir);

        $loader = new YamlFileLoader($container, $fileLocator);

        try {
            $loader->load('boot.yml');
            $configFiles = $container->getParameter('config.files');
            foreach ($configFiles as $configFile) {
                $loader->load($configFile);
            }
        } catch (\Exception $e) {
            throw new SmartlingConfigException('Error in YAML configuration file', 0, $e);
        }

        $this->containerInstance = $container;
    }
}
