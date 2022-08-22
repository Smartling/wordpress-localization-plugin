<?php

namespace Smartling\DbAl;

use Smartling\Helpers\SiteHelper;
use Smartling\MonologWrapper\MonologWrapper;
use Smartling\Services\GlobalSettingsManager;
use Smartling\Vendor\Psr\Log\LoggerInterface;

/**
 * Class EntityManagerAbstract
 *
 * @package Smartling\DbAl
 */
abstract class EntityManagerAbstract
{

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var SmartlingToCMSDatabaseAccessWrapperInterface
     */
    private $dbal;

    /**
     * @var int
     */
    private $pageSize;

    /**
     * @var SiteHelper
     */
    private $siteHelper;

    /**
     * @var LocalizationPluginProxyInterface
     */
    private $pluginProxy;

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @return SmartlingToCMSDatabaseAccessWrapperInterface
     */
    public function getDbal()
    {
        return $this->dbal;
    }

    /**
     * @param SmartlingToCMSDatabaseAccessWrapperInterface $dbal
     */
    public function setDbal($dbal)
    {
        $this->dbal = $dbal;
    }

    /**
     * @return int
     */
    public function getPageSize()
    {
        return $this->pageSize < 1 ? GlobalSettingsManager::getPageSizeDefault() : $this->pageSize;
    }

    /**
     * @param int $pageSize
     */
    public function setPageSize($pageSize)
    {
        $this->pageSize = $pageSize;
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
     * @return LocalizationPluginProxyInterface
     */
    public function getPluginProxy()
    {
        return $this->pluginProxy;
    }

    /**
     * @param LocalizationPluginProxyInterface $pluginProxy
     */
    public function setPluginProxy($pluginProxy)
    {
        $this->pluginProxy = $pluginProxy;
    }

    /**
     * @param SmartlingToCMSDatabaseAccessWrapperInterface $dbal
     * @param int                                          $pageSize
     * @param SiteHelper                                   $siteHelper
     * @param LocalizationPluginProxyInterface             $localizationProxy
     */
    public function __construct($dbal, $pageSize, SiteHelper $siteHelper, $localizationProxy)
    {
        $this->logger = MonologWrapper::getLogger(get_called_class());
        $this->setDbal($dbal);
        $this->setPageSize($pageSize);
        $this->setSiteHelper($siteHelper);
        $this->setPluginProxy($localizationProxy);
    }

    protected function fetchData($query): array
    {
        $results = [];
        $res = $this->getDbal()->fetch($query);
        if (is_array($res) && 0 < count($res)) {
            foreach ($res as $row) {
                $results[] = $this->dbResultToEntity((array)$row);
            }
        }

        return $results;
    }


    /**
     * @param string $query
     */
    public function logQuery($query)
    {
        $this->getLogger()->debug($query);
    }

    /**
     * @param array $dbRow
     * @return static
     */
    abstract protected function dbResultToEntity(array $dbRow);
}