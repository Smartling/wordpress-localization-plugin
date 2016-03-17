<?php

namespace Smartling;

use Smartling\Base\ExportedAPI;
use Smartling\Exception\SmartlingConfigException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Config\FileLocator;

trait DITrait
{
    /**
     * @var ContainerBuilder $container
     */
    private static $containerInstance = null;

    /**
     * Initializes DI Container from YAML config file
     *
     * @throws SmartlingConfigException
     */
    protected static function initContainer()
    {
        $container = new ContainerBuilder();

        self::setCoreParameters($container);

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

        self::$containerInstance = $container;

        /**
         * Exposing reference to DI interface
         */
        do_action(ExportedAPI::ACTION_SMARTLING_BEFORE_INITIALIZE_EVENT, self::$containerInstance);

        self::$loggerInstance = $container->get('logger');
    }

    /**
     * Extracts mixed from container
     *
     * @param string $id
     * @param bool   $isParam
     *
     * @return mixed
     */
    protected function fromContainer($id, $isParam = false)
    {
        $container = self::getContainer();
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
    public static function getContainer()
    {
        if (null === self::$containerInstance) {
            self::initContainer();
        }

        return self::$containerInstance;
    }
}