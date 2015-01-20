<?php
/**
 * Created by PhpStorm.
 * User: sergey@slepokurov.com
 * Date: 20.01.2015
 * Time: 14:46
 */

namespace Smartling\WP;

use Monolog\Logger;
use Smartling\DbAl\MultiligualPressProConnector;
use Smartling\Helpers\PluginInfo;

abstract class WPAbstract {
    /**
     * @var Logger
     */
    private $logger;
    /**
     * @var PluginInfo
     */
    private $pluginInfo;

    /**
     * @var MultiligualPressProConnector
     */
    private $multiLingualConnector;


    public function __construct(Logger $logger, MultiligualPressProConnector $multiLingualConnector, PluginInfo $pluginInfo) {
        $this->logger = $logger;
        $this->multiLingualConnector = $multiLingualConnector;
        $this->pluginInfo = $pluginInfo;
    }

    /**
     * @return Logger
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
     * @return MultiligualPressProConnector
     */
    public function getMultiLingualConnector()
    {
        return $this->multiLingualConnector;
    }

    public function view($data = null) {
        $class = get_called_class();
        $class = str_replace("Smartling\\WP\\", "", $class);
        require_once plugin_dir_path( __FILE__ ) . 'view/' . $class . ".php";
    }
}