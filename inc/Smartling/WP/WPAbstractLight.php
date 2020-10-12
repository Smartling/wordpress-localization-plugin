<?php

namespace Smartling\WP;

use Smartling\Exception\SmartlingIOException;
use Smartling\Helpers\PluginInfo;
use Smartling\MonologWrapper\MonologWrapper;

class WPAbstractLight
{
    protected $logger;
    protected $pluginInfo;
    protected $viewData;

    public function __construct(PluginInfo $pluginInfo)
    {
        $this->logger = MonologWrapper::getLogger(static::class);
        $this->pluginInfo = $pluginInfo;
    }

    /**
     * @param mixed $data
     */
    public function view($data = null)
    {
        $this->viewData = $data;
        $class = static::class;
        $class = str_replace(['Smartling\\WP\\Controller\\', 'Controller'], '', $class);

        $this->renderViewScript($class . '.php');
    }

    /**
     * @param string $script
     * @throws SmartlingIOException
     */
    public function renderViewScript($script)
    {
        $filename = plugin_dir_path(__FILE__) . 'View/' . $script;

        if (!file_exists($filename) || !is_file($filename) || !is_readable($filename)) {
            throw new SmartlingIOException(vsprintf('Requested view file (%s) not found.', [$filename]));
        }

        /** @noinspection PhpIncludeInspection */
        require_once $filename;
    }
}
