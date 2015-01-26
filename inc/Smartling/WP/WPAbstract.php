<?php

namespace Smartling\WP;

use Psr\Log\LoggerInterface;
use Smartling\DbAl\LocalizationPluginProxyInterface;
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
     * @var LocalizationPluginProxyInterface
     */
    private $multiLingualConnector;

    /**
     * Constructor
     * @param LoggerInterface $logger
     * @param LocalizationPluginProxyInterface $multiLingualConnector
     * @param PluginInfo $pluginInfo
     */
    public function __construct(
        LoggerInterface $logger,
        LocalizationPluginProxyInterface $multiLingualConnector,
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
     * @return LocalizationPluginProxyInterface
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