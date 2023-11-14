<?php

namespace Smartling\Helpers;

use Smartling\Settings\SettingsManager;

/**
 * Class PluginInfo
 *
 * @package Smartling\Helpers
 */
class PluginInfo
{

    /**
     * @var SettingsManager
     */
    private $settingsManager;
    /**
     * @var string
     */
    private $name;
    /**
     * @var string
     */
    private $version;
    /**
     * @var string
     */
    private $domain;
    /**
     * @var string
     */
    private $url;
    private string $dir = SMARTLING_PLUGIN_DIR;

    /**
     * @param string          $name
     * @param string          $version
     * @param string          $url
     * @param string          $domain
     * @param SettingsManager $settingsManager
     */
    public function __construct($name, $version, $url, $domain, $settingsManager)
    {
        $this->name = $name;
        $this->version = $version;
        $this->url = $url;
        $this->domain = $domain;
        $this->settingsManager = $settingsManager;
    }

    /**
     * @return SettingsManager
     */
    public function getSettingsManager()
    {
        return $this->settingsManager;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * @return string
     */
    public function getDomain()
    {
        return $this->domain;
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    public function getDir(): string
    {
        return $this->dir;
    }
}
