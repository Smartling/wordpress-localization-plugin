<?php

namespace Smartling\WP\Controller;

use Psr\Log\LoggerInterface;
use Smartling\ApiWrapperInterface;
use Smartling\MonologWrapper\MonologWrapper;
use Smartling\Settings\SettingsManager;
use Smartling\WP\WPHookInterface;

/**
 * Class LiveNotificationController
 * @package Smartling\WP\Controller
 */
class LiveNotificationController implements WPHookInterface
{

    /**
     * @var ApiWrapperInterface
     */
    private $apiWrapper;

    /**
     * @var SettingsManager
     */
    private $settingsManager;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @return ApiWrapperInterface
     */
    public function getApiWrapper()
    {
        return $this->apiWrapper;
    }

    /**
     * @param ApiWrapperInterface $apiWrapper
     *
     * @return LiveNotificationController
     */
    public function setApiWrapper($apiWrapper)
    {
        $this->apiWrapper = $apiWrapper;

        return $this;
    }

    /**
     * @return SettingsManager
     */
    public function getSettingsManager()
    {
        return $this->settingsManager;
    }

    /**
     * @param SettingsManager $settingsManager
     *
     * @return LiveNotificationController
     */
    public function setSettingsManager($settingsManager)
    {
        $this->settingsManager = $settingsManager;

        return $this;
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @param LoggerInterface $logger
     *
     * @return LiveNotificationController
     */
    public function setLogger($logger)
    {
        $this->logger = $logger;

        return $this;
    }


    public function __construct(ApiWrapperInterface $apiWrapper, SettingsManager $settingsManager)
    {
        $this
            ->setApiWrapper($apiWrapper)
            ->setSettingsManager($settingsManager);

        $this->setLogger(MonologWrapper::getLogger(__CLASS__));
    }

    /**
     * Registers wp hook handlers. Invoked by wordpress.
     * @return void
     */
    public function register()
    {
        // TODO: Implement register() method.
    }
}