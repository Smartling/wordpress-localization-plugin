<?php

namespace Smartling\DbAl;

use Psr\Log\LoggerInterface;
use Smartling\Helpers\SiteHelper;

class MultiligualPressProConnector extends MultilangPluginAbstract
{

    const MULTILINGUAL_PRESS_PRO_SITE_OPTION = 'inpsyde_multilingual';

    protected static $_blogLocalesCache = array();

    private function cacheLocales()
    {
        if (empty(self::$_blogLocalesCache)) {
            $rawValue = get_site_option(self::MULTILINGUAL_PRESS_PRO_SITE_OPTION, false, false);

            if (false === $rawValue) {
                throw new \Exception('Multilingual press PRO is not installed/configured.');
            } else {
                foreach ($rawValue as $blogId => $item)
                {
                    self::$_blogLocalesCache[$blogId] = array(
                        "text" => $item['text'],
                        "lang" => $item['lang']
                    );
                }
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function getLocales() {
        if (!function_exists('get_site_option')) {
            $this->directFunFallback('Direct run detected. Required run as Wordpress plugin.');
        }
        $this->cacheLocales();

        $locales = array();
        foreach(self::$_blogLocalesCache as $blogId => $blogLocale) {
            $locales[] = $blogLocale["text"];
        }

        return $locales;
    }

    /**
     * @inheritdoc
     */
    public function getBlogLocaleById($blogId)
    {
        if (!function_exists('get_site_option')) {
            $this->directFunFallback('Direct run detected. Required run as Wordpress plugin.');
        }

        $this->cacheLocales();

        $this->helper->switchBlogId($blogId);

        $locale = self::$_blogLocalesCache[$this->helper->getCurrentBlogId()];

        $this->helper->restoreBlogId();

        return $locale["lang"];
    }

    /**
     * @inheritdoc
     */
    public function getLnkedBlogsByBlodId($blogId)
    {
        // TODO: Implement getLnkedBlogsByBlodId() method.
    }

    /**
     * @inheritdoc
     */
    public function __construct(LoggerInterface $logger, SiteHelper $helper, array $ml_plugin_statuses)
    {
        parent::__construct($logger, $helper, $ml_plugin_statuses);

        if (false === $ml_plugin_statuses['multilingual-press-pro']) {
            throw new \Exception('Active plugin not found Exception');
        }
    }
}