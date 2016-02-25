<?php

namespace Smartling\WP;

use Psr\Log\LoggerInterface;
use Smartling\Base\SmartlingCore;
use Smartling\Bootstrap;
use Smartling\Helpers\DiagnosticsHelper;
use Smartling\Helpers\PluginInfo;
use Smartling\Helpers\SimpleStorageHelper;

/**
 * Class JobEngine
 *
 * @package Smartling\WP
 */
class JobEngine implements WPHookInterface
{

    const CRON_HOOK = 'smartling_cron_task';
    const LOCK_NAME = 'smartling-cron.pid';

    const CRON_INTERVAL = 300;
    const CRON_TTL      = 5;

    public function __construct($logger, $pluginInfo)
    {
        $this->logger = $logger;
        $this->pluginInfo = $pluginInfo;
    }

    public function install()
    {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), ((self::CRON_INTERVAL / 60) . 'm'), self::CRON_HOOK);
        }
    }

    public function uninstall()
    {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    public function register()
    {
        if (!DiagnosticsHelper::isBlocked()) {
            add_action(self::CRON_HOOK, [$this, 'doWork']);
        }
    }

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var PluginInfo
     */
    private $pluginInfo;

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
     * @return string
     */
    public function getLockFileName()
    {
        return $this->getPluginInfo()
                    ->getDir() . DIRECTORY_SEPARATOR . self::LOCK_NAME;
    }

    /**
     * Cron job trigger handler
     */
    public function doWork()
    {
        $curLockStatus = SimpleStorageHelper::get(self::LOCK_NAME, 0);
        $now = time();
        $this->getLogger()
             ->info('Cron Job initiated');
        if ($now > $curLockStatus + self::CRON_INTERVAL + self::CRON_TTL) {
            SimpleStorageHelper::set(self::LOCK_NAME, $now);
            $this->job();
        }
        $this->getLogger()
             ->info('Cron Job Finished');
    }


    /**
     * Checks & Downloads completed translations
     */
    public function job()
    {
        /**
         * @var SmartlingCore $ep
         */
        $ep = Bootstrap::getContainer()
                       ->get('entrypoint');
        $ep->bulkCheck();
    }
}