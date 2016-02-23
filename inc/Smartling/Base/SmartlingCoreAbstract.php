<?php

namespace Smartling\Base;

use Psr\Log\LoggerInterface;
use Smartling\ApiWrapperInterface;
use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\Helpers\Cache;
use Smartling\Helpers\CustomMenuContentTypeHelper;
use Smartling\Helpers\SiteHelper;
use Smartling\Processors\ContentEntitiesIOFactory;
use Smartling\Queue\QueueInterface;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionManager;

/**
 * Class SmartlingCoreAbstract
 *
 * @package Smartling\Base
 */
abstract class SmartlingCoreAbstract
{
    /**
     * Mode to send data to smartling directly
     */
    const SEND_MODE_STREAM = 1;

    /**
     * Mode to send data to smartling via temporary file
     */
    const SEND_MODE_FILE = 2;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var SubmissionManager
     */
    private $submissionManager;

    /**
     * @var SettingsManager
     */
    private $settings;

    /**
     * @var SiteHelper
     */
    private $siteHelper;

    /**
     * @var ApiWrapperInterface
     */
    private $apiWrapper;

    /**
     * @var ContentEntitiesIOFactory
     */
    private $contentIoFactory;

    /**
     * @var LocalizationPluginProxyInterface
     */
    private $multilangProxy;

    /**
     * @var Cache
     */
    private $cache;

    /**
     * @var CustomMenuContentTypeHelper
     */
    private $customMenuHelper;

    /**
     * @var SettingsManager
     */
    private $settingsManager;

    /**
     * @var QueueInterface
     */
    private $queue;

    /**
     * @return Cache
     */
    public function getCache()
    {
        return $this->cache;
    }

    /**
     * @param Cache $cache
     */
    public function setCache(Cache $cache)
    {
        $this->cache = $cache;
    }

    /**
     * @return ApiWrapperInterface
     */
    public function getApiWrapper()
    {
        return $this->apiWrapper;
    }

    /**
     * @param ApiWrapperInterface $apiWrapper
     */
    public function setApiWrapper(ApiWrapperInterface $apiWrapper)
    {
        $this->apiWrapper = $apiWrapper;
    }

    /**
     * @return LocalizationPluginProxyInterface
     */
    public function getMultilangProxy()
    {
        return $this->multilangProxy;
    }

    /**
     * @param LocalizationPluginProxyInterface $multilangProxy
     */
    public function setMultilangProxy($multilangProxy)
    {
        $this->multilangProxy = $multilangProxy;
    }

    /**
     * @return SiteHelper
     */
    public function getSiteHelper()
    {
        return $this->siteHelper;
    }

    /**
     * @param SiteHelper $siteHelper
     */
    public function setSiteHelper(SiteHelper $siteHelper)
    {
        $this->siteHelper = $siteHelper;
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
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @return SubmissionManager
     */
    public function getSubmissionManager()
    {
        return $this->submissionManager;
    }

    /**
     * @param SubmissionManager $submissionManager
     */
    public function setSubmissionManager(SubmissionManager $submissionManager)
    {
        $this->submissionManager = $submissionManager;
    }

    /**
     * @return SettingsManager
     */
    public function getSettings()
    {
        return $this->settings;
    }

    /**
     * @param SettingsManager $settings
     */
    public function setSettings(SettingsManager $settings)
    {
        $this->settings = $settings;
    }

    /**
     * @return ContentEntitiesIOFactory
     */
    public function getContentIoFactory()
    {
        return $this->contentIoFactory;
    }

    /**
     * @param ContentEntitiesIOFactory $contentIoFactory
     */
    public function setContentIoFactory($contentIoFactory)
    {
        $this->contentIoFactory = $contentIoFactory;
    }

    /**
     * @return CustomMenuContentTypeHelper
     */
    public function getCustomMenuHelper()
    {
        return $this->customMenuHelper;
    }

    /**
     * @param CustomMenuContentTypeHelper $customMenuHelper
     */
    public function setCustomMenuHelper($customMenuHelper)
    {
        $this->customMenuHelper = $customMenuHelper;
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
     */
    public function setSettingsManager($settingsManager)
    {
        $this->settingsManager = $settingsManager;
    }

    /**
     * @return QueueInterface
     */
    public function getQueue()
    {
        return $this->queue;
    }

    /**
     * @param QueueInterface $queue
     */
    public function setQueue(QueueInterface $queue)
    {
        $this->queue = $queue;
    }
}