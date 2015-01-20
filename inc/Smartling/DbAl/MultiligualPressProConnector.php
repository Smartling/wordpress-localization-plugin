<?php
/**
 * Created by PhpStorm.
 * User: para
 * Date: 15.01.15
 * Time: 17:08
 */

namespace Smartling\DbAl;

use Smartling\Exception\SmartlingConfigException;
use Smartling\Helpers\SiteHelper;

class MultiligualPressProConnector extends MultilangPluginAbstract
{

    private $helper = null;

    private $logger = null;

    protected static $_blogLocalesCache = array();


    private function cacheLocales()
    {
        if (empty(self::$_blogLocalesCache)) {
            $raw = get_site_option('inpsyde_multilingual', false, false);

            if (false === $raw) {
                throw new \Exception('Multilingual press PRO is not installed/configured.');
            } else {
                foreach ($raw as $blogId => $item)
                {
                    self::$_blogLocalesCache[$blogId] = $item['lang'];
                }
            }
        }
    }

    function getBlogLocaleById($blogId)
    {
        if (!function_exists('get_site_option')) {
            $this->fallbackErrorMessage('Direct run detected. Required run as Wordpress plugin.');
        }

        $this->cacheLocales();

        $this->helper->switchBlogId($blogId);

        $locale = self::$_blogLocalesCache[$this->helper->getCurrentBlogId()];

        $this->helper->restoreBlogId();

        return $locale;
    }

    function getLnkedBlogsByBlodId($blogId)
    {
        // TODO: Implement getLnkedBlogsByBlodId() method.
    }

    function __construct($logger, SiteHelper $helper, $ml_plugin_statuses)
    {
        if (false === $ml_plugin_statuses['multilingual-press-pro']) {
            throw new \Exception('Active plugin not found Exception');
        }

        $this->logger = $logger;
        $this->helper = $helper;
    }
}