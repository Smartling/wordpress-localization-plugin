<?php

namespace Smartling\WP;

use Psr\Log\LoggerInterface;
use Smartling\DbAl\MultilingualPluginProxyInterface;
use Smartling\Helpers\PluginInfo;

abstract class WPAbstract {

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var PluginInfo
     */
    private $pluginInfo;

    /**
     * @var MultilingualPluginProxyInterface
     */
    private $multiLingualConnector;

    /**
     * Constructor
     * @param LoggerInterface $logger
     * @param MultilingualPluginProxyInterface $multiLingualConnector
     * @param PluginInfo $pluginInfo
     */
    public function __construct(
        LoggerInterface $logger,
        MultilingualPluginProxyInterface $multiLingualConnector,
        PluginInfo $pluginInfo
    ) {
        $this->logger = $logger;
        $this->multiLingualConnector = $multiLingualConnector;
        $this->pluginInfo = $pluginInfo;
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @return PluginInfo
     */
    public function getPluginInfo()
    {
        return $this->pluginInfo;
    }

    /**
     * @return MultilingualPluginProxyInterface
     */
    public function getConnector()
    {
        return $this->multiLingualConnector;
    }

    /**
     * @param null $data
     */
    public function view($data = null) {
        $class = get_called_class();
        $class = str_replace("Smartling\\WP\\Controller", "", $class);

        $class = str_replace("Controller", "", $class);

        require_once plugin_dir_path( __FILE__ ) . 'View/' . $class . ".php";
    }
}