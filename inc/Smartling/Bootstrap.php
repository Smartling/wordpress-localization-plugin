<?php

namespace Smartling;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class Bootstrap {

    /**
     * @var ContainerBuilder $container
     */
    private static $_container = null;

    public static function getContainer()
    {
        if (is_null(self::$_container)) {

            $container = new ContainerBuilder();

            self::setCoreParameters($container);

            $configDir = SMARTLING_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'inc';

            $fileLocator = new FileLocator($configDir);

            $loader = new YamlFileLoader($container, $fileLocator);

            $loader->load('services.yml');

            self::$_container = $container;
        }

        return self::$_container;
    }

    private static function setCoreParameters(ContainerBuilder $container)
    {
        // plugin dir (to use in config file)
        $container->setParameter('plugin.dir', SMARTLING_PLUGIN_DIR);
    }
}