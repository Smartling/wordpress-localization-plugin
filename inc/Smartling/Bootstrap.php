<?php

namespace Smartling;

use Smartling\WP\WPHookInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Smartling\Exception\MultilingualPluginNotFoundException;
use Smartling\Exception\SmartlingConfigException;

class Bootstrap {

    /**
     * @var ContainerBuilder $container
     */
    private static $_container = null;

    /**
     * Initializes DI Container from YAML config file
     * @throws SmartlingConfigException
     */
    protected function _initContainer()
    {
        $container = new ContainerBuilder();

        self::setCoreParameters($container);

        $configDir = SMARTLING_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'inc';

        $fileLocator = new FileLocator($configDir);

        $loader = new YamlFileLoader($container, $fileLocator);

        try {
            $loader->load('services.yml');
        } catch (\Exception $e) {
            throw new SmartlingConfigException('Error in YAML configuration file', 0, $e);
        }

        self::$_container = $container;
    }

    /**
     * @return ContainerBuilder
     * @throws SmartlingConfigException
     */
    public static function getContainer()
    {
        if (is_null(self::$_container)) {
            self::_initContainer();
        }

        return self::$_container;
    }

    private static function setCoreParameters(ContainerBuilder $container)
    {
        // plugin dir (to use in config file)
        $container->setParameter('plugin.dir', SMARTLING_PLUGIN_DIR);

        $pluginUrl = '';

        if(defined(SMARTLING_CLI_EXECUTION) && false === SMARTLING_CLI_EXECUTION) {
            $pluginUrl = plugin_dir_url(SMARTLING_PLUGIN_DIR . DIRECTORY_SEPARATOR . '..');
        }

        $container->setParameter('plugin.url', $pluginUrl);
    }

    public function registerHooks() {
        $hooks = $this->getContainer()->getParameter('wp.hooks');
        foreach($hooks as $hook) {
            $object = $this->getContainer()->get($hook);
            if($object instanceof WPHookInterface) {
                $object->register();
            }
        }
    }

    public function load() {
        $this->detectMultilangPlugins();

        if (defined('SMARTLING_CLI_EXECUTION') && SMARTLING_CLI_EXECUTION === false) {
            $this->registerHooks();
            $this->run();
        }

    }

    public function activate() {
        $this->getContainer()->get('site.db')->install();
    }

    public function deactivate() {
    }

    public function uninstall() {
        $this->getContainer()->get('plugin.options')->uninstall();
        $this->getContainer()->get('site.db')->uninstall();
    }

    /**
     * @throws MultilingualPluginNotFoundException
     */
    public function detectMultilangPlugins()
    {
        $di_container = self::getContainer();

        $logger = $di_container->get('logger');

        $mlPluginsStatuses =
            array(
                'multilingual-press-pro'    => false,
                'polylang'                  => false,
                'wpml'                      => false,
            );

        $logger->addInfo('Searching for Wordpress multilingual plugins');

        $_found = false;

        if (class_exists('Mlp_Load_Controller', false)) {
            $mlPluginsStatuses['multilingual-press-pro'] = true;
            $logger->addInfo('found "multilingual-press-pro" plugin');

            $_found = true;
        }

        if (false === $_found) {
            throw new MultilingualPluginNotFoundException('No active multilingual plugins found.');
        }

        $di_container->set('multilang_plugins', $mlPluginsStatuses);
   }

    public function run()
    {

    }
}
