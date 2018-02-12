<?php

namespace Smartling\Base;

use Psr\Log\LoggerInterface;
use Smartling\ApiWrapperInterface;
use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\Helpers\Cache;
use Smartling\Helpers\ContentHelper;
use Smartling\Helpers\ContentSerializationHelper;
use Smartling\Helpers\CustomMenuContentTypeHelper;
use Smartling\Helpers\FieldsFilterHelper;
use Smartling\Helpers\SiteHelper;
use Smartling\Helpers\TranslationHelper;
use Smartling\MonologWrapper\MonologWrapper;
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
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var SubmissionManager
     */
    private $submissionManager;

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
     * @var ContentHelper
     */
    private $contentHelper;

    /**
     * @var TranslationHelper;
     */
    private $translationHelper;

    /**
     * @var FieldsFilterHelper
     */
    private $fieldsFilter;

    /**
     * @var ContentSerializationHelper
     */
    private $contentSerializationHelper;

    /**
     * SmartlingCoreAbstract constructor.
     */
    public function __construct()
    {
        $this->logger = MonologWrapper::getLogger(get_called_class());
    }

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

    /**
     * @return ContentHelper
     */
    public function getContentHelper()
    {
        return $this->contentHelper;
    }

    /**
     * @param ContentHelper $contentHelper
     */
    public function setContentHelper($contentHelper)
    {
        $this->contentHelper = $contentHelper;
    }

    /**
     * @return TranslationHelper
     */
    public function getTranslationHelper()
    {
        return $this->translationHelper;
    }

    /**
     * @param TranslationHelper $translationHelper
     */
    public function setTranslationHelper($translationHelper)
    {
        $this->translationHelper = $translationHelper;
    }

    /**
     * @return FieldsFilterHelper
     */
    public function getFieldsFilter()
    {
        return $this->fieldsFilter;
    }

    /**
     * @param FieldsFilterHelper $fieldsFilter
     */
    public function setFieldsFilter($fieldsFilter)
    {
        $this->fieldsFilter = $fieldsFilter;
    }

    /**
     * @return ContentSerializationHelper
     */
    public function getContentSerializationHelper()
    {
        return $this->contentSerializationHelper;
    }

    /**
     * @param ContentSerializationHelper $contentSerializationHelper
     */
    public function setContentSerializationHelper($contentSerializationHelper)
    {
        $this->contentSerializationHelper = $contentSerializationHelper;
    }
}