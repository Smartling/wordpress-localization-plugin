<?php

namespace Smartling\DbAl;

use Psr\Log\LoggerInterface;
use Smartling\Helpers\SiteHelper;

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
     * @param LoggerInterface $logger
     */
    public function setLogger($logger)
    {
        $this->logger = $logger;
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
        return $this->pageSize;
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
     * @param LoggerInterface                              $logger
     * @param SmartlingToCMSDatabaseAccessWrapperInterface $dbal
     * @param int                                          $pageSize
     * @param SiteHelper                                   $siteHelper
     * @param LocalizationPluginProxyInterface             $localizationPluginProxyInterface
     */
    public function __construct(
        LoggerInterface $logger,
        $dbal,
        $pageSize,
        SiteHelper $siteHelper,
        $localizationPluginProxyInterface
    )
    {
        $this->setLogger($logger);
        $this->setDbal($dbal);
        $this->setPageSize($pageSize);
        $this->setSiteHelper($siteHelper);
        $this->setPluginProxy($localizationPluginProxyInterface);
    }

    protected function fetchData($query)
    {
        $results = [];
        $res = $this->getDbal()
                    ->fetch($query);
        if (is_array($res)) {
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
        if (true === $this->getDbal()
                          ->needRawSqlLog()
        ) {
            $this->getLogger()
                 ->debug($query);
        }
    }

    abstract protected function dbResultToEntity(array $dbRow);
}