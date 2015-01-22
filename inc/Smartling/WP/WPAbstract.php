<?php

namespace Smartling\WP;

use Psr\Log\LoggerInterface;
use Smartling\DbAl\MultilangPluginProxy;
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
     * @var MultilangPluginProxy
     */
    private $multiLingualConnector;

    /**
     * Constructor
     * @param LoggerInterface $logger
     * @param MultilangPluginProxy $multiLingualConnector
     * @param PluginInfo $pluginInfo
     */
    public function __construct(
        LoggerInterface $logger,
        MultilangPluginProxy $multiLingualConnector,
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
     * @return MultilangPluginProxy
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