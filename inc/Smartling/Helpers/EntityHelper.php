<?php

namespace Smartling\Helpers;

use Psr\Log\LoggerInterface;
use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\Exception\SmartlingDbException;
use Smartling\MonologWrapper\MonologWrapper;
use Smartling\Settings\SettingsManager;

/**
 * Class EntityHelper
 *
 * @package Smartling\Helpers
 */
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
    public function setSiteHelper($siteHelper)
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
     * @return SettingsManager
     */
    public function getSettingsManager()
    {
        return $this->getPluginInfo()->getSettingsManager();
    }

    /**
     * Returns id of original content linked to given or throws the exception
     *
     * @param int    $id
     * @param string $type
     *
     * @return int
     * @throws SmartlingDbException
     */
    public function getOriginalContentId($id, $type = 'post')
    {

        $curBlog = $this->getSiteHelper()
                        ->getCurrentBlogId();
        $defBlog = $this->getSettingsManager()
                        ->getLocales()
                        ->getDefaultBlog();

        if ($curBlog === $defBlog) {
            //TODO mb some collision
            return $id;
        }

        $linkedObjects = $this->getConnector()
                              ->getLinkedObjects($curBlog, $id, $type);

        foreach ($linkedObjects as $blogId => $contentId) {
            if ($blogId === $defBlog) {
                return $contentId;
            }
        }

        $message = vsprintf('For given content-type: \'%s\' id:%s in blog %s link to original content id not found',
            [
                $type,
                $id,
                $curBlog,
            ]);

        $this->getLogger()
             ->error($message);

        throw new SmartlingDbException ($message);
    }

    /**
     * @param int    $id
     * @param string $type
     *
     * @return int
     * @throws \Exception not found original
     */
    public function getTarget($id, $targetBlog, $type = 'post')
    {
        if ($this->getSiteHelper()
                 ->getCurrentBlogId() === $targetBlog
        ) {
            return $id;
        }

        $linked = $this->getConnector()
                       ->getLinkedObjects($this->getSiteHelper()
                                               ->getCurrentBlogId(), $id, $type);
        foreach ($linked as $key => $item) {
            if ($key === $targetBlog) {
                return $item;
            }
        }

        return null;
    }
}