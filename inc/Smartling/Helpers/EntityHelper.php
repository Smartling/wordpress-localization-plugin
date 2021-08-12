<?php

namespace Smartling\Helpers;

use Psr\Log\LoggerInterface;
use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\Exception\SmartlingDbException;
use Smartling\MonologWrapper\MonologWrapper;
use Smartling\Settings\SettingsManager;

class EntityHelper
{
    /**
     * @var PluginInfo
     */
    private $pluginInfo;

    /**
     * @return LocalizationPluginProxyInterface
     */
    private $connector;

    /**
     * @var SiteHelper
     */
    private $siteHelper;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * EntityHelper constructor.
     */
    public function __construct() {
        $this->logger = MonologWrapper::getLogger(get_called_class());
    }

    /**
     * @return PluginInfo
     */
    public function getPluginInfo()
    {
        return $this->pluginInfo;
    }

    /**
     * @param PluginInfo $pluginInfo
     */
    public function setPluginInfo($pluginInfo)
    {
        $this->pluginInfo = $pluginInfo;
    }

    /**
     * @return LocalizationPluginProxyInterface
     */
    public function getConnector()
    {
        return $this->connector;
    }

    /**
     * @param LocalizationPluginProxyInterface $connector
     */
    public function setConnector($connector)
    {
        $this->connector = $connector;
    }

    public function getSiteHelper(): SiteHelper
    {
        return $this->siteHelper;
    }

    /**
     * @param SiteHelper $siteHelper
     */
    public function setSiteHelper($siteHelper)
    {
        $this->siteHelper = $siteHelper;
    }

    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * @return SettingsManager
     */
    public function getSettingsManager()
    {
        return $this->getPluginInfo()->getSettingsManager();
    }
}
