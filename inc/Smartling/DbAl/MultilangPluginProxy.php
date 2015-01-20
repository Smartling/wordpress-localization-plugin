<?php

namespace Smartling\DbAl;

use Psr\Log\LoggerInterface;
use Smartling\Helpers\SiteHelper;

/**
 * Interface MultilangPluginProxy
 *
 * @package Smartling\DbAl
 */
interface MultilangPluginProxy {

    /**
     * Constructor
     * @param LoggerInterface $logger
     * @param SiteHelper $helper
     * @param array $ml_plugin_statuses
     */
    function __construct(LoggerInterface $logger, SiteHelper $helper, array $ml_plugin_statuses);

    /**
     * Retrieves locale from site option
     * @return array
     */
    function getLocales();

    /**
     * Retrieves locale from site option
     * @param integer $blogId
     * @return string
     */
    function getBlogLocaleById($blogId);

    /**
     * Retrieves blog ids linked to given blog
     * @param integer $blogId
     * @return array
     */
    function getLnkedBlogsByBlodId($blogId);
}