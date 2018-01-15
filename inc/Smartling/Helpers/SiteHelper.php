<?php

namespace Smartling\Helpers;

use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\Exception\BlogNotFoundException;
use Smartling\Exception\SmartlingDirectRunRuntimeException;
use Smartling\MonologWrapper\MonologWrapper;

/**
 * Class SiteHelper
 * Helps to manipulate with Sites and Blogs
 * @package Smartling\Helpers
 */
class SiteHelper
{

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var int;
     */
    private $initialBlogId = 0;

    /**
     * @return int
     */
    public function getInitialBlogId()
    {
        return $this->initialBlogId;
    }

    /**
     * Instantiates SiteHelper object.
     */
    public function __construct()
    {
        $this->logger = MonologWrapper::getLogger(get_called_class());;

        $this->initialBlogId = $this->getCurrentBlogId();
    }

    /**
     * @var array
     */
    protected static $_siteCache = [];

    /**
     * @var array
     */
    protected static $_flatBlogIdCache = [];

    /**
     * Fallback for direct run if Wordpress functionality is not reachable
     *
     * @throws SmartlingDirectRunRuntimeException
     */
    private function directRunDetectedFallback()
    {
        $message = 'Direct run detected. Required run as Wordpress plugin.';

        $this->getLogger()->error($message);

        throw new SmartlingDirectRunRuntimeException($message);
    }

    private function cacheSites()
    {
        if (empty(self::$_siteCache)) {
            $sites = get_sites(['number' => 1000]);
            foreach ($sites as $site) {

                self::$_siteCache[$site->site_id][] = $site->blog_id;
                self::$_flatBlogIdCache[] = (int)$site->blog_id;
            }
        }
    }

    /**
     * @return array
     * @throws SmartlingDirectRunRuntimeException
     */
    public function listSites()
    {
        !function_exists('get_sites')
        && $this->directRunDetectedFallback();

        $this->cacheSites();

        return array_keys(self::$_siteCache);
    }

    /**
     * @param int $siteId
     *
     * @return array
     * @throws SmartlingDirectRunRuntimeException
     * @throws InvalidArgumentException
     */
    public function listBlogs($siteId = 1)
    {
        !function_exists('get_sites')
        && $this->directRunDetectedFallback();

        $this->cacheSites();

        if (isset(self::$_siteCache[$siteId])) {
            return self::$_siteCache[$siteId];
        } else {
            $message = 'Invalid site_id value set.';
            throw new \InvalidArgumentException($message);
        }
    }

    /**
     * @return array
     */
    public function listBlogIdsFlat()
    {
        $this->cacheSites();

        return self::$_flatBlogIdCache;
    }

    /**
     * @return integer
     * @throws SmartlingDirectRunRuntimeException
     */
    public function getCurrentSiteId()
    {
        if (function_exists('get_current_site')) {
            return get_current_site()->id;
        } else {
            $this->directRunDetectedFallback();
        }
    }

    /**
     * @return int
     * @throws SmartlingDirectRunRuntimeException
     */
    public function getCurrentBlogId()
    {
        if (function_exists('get_current_blog_id')) {
            return (int)get_current_blog_id();
        } else {
            $this->directRunDetectedFallback();
        }
    }

    /**
     * @return string
     * @throws SmartlingDirectRunRuntimeException
     */
    public function getCurrentUserLogin()
    {
        if (function_exists('wp_get_current_user')) {
            $user = wp_get_current_user();
            if ($user) {
                return $user->user_login;
            }

            return null;
        } else {
            $this->directRunDetectedFallback();
        }
    }

    /**
     * @param $blogId
     *
     * @throws BlogNotFoundException
     * @throws SmartlingDirectRunRuntimeException
     */
    public function switchBlogId($blogId)
    {
        $this->cacheSites();

        if (!in_array($blogId, self::$_flatBlogIdCache)) {
            $message = vsprintf('Invalid blogId value. Got %s, expected one of [%s]',
                [$blogId, implode(',', self::$_flatBlogIdCache)]);

            throw new BlogNotFoundException($message);
        }

        if (function_exists('switch_to_blog')) {
            switch_to_blog($blogId);
        } else {
            $this->directRunDetectedFallback();
        }

    }

    public function restoreBlogId()
    {
        if (!function_exists('restore_current_blog') || !function_exists('ms_is_switched')) {
            $this->directRunDetectedFallback();
        }

        if (false === ms_is_switched()) {
            $message = 'Blog was not switched previously';
            throw new \LogicException($message);
        }

        restore_current_blog();
    }

    /**
     * @param $blogId
     *
     * @return string
     * @throws BlogNotFoundException
     */
    private function getBlogNameById($blogId)
    {
        $this->switchBlogId($blogId);
        $label = get_bloginfo('Name');
        $this->restoreBlogId();

        return $label;
    }

    /**
     * @param $localizationPluginProxyInterface
     * @param $blogId
     *
     * @return string
     * @throws BlogNotFoundException
     */
    public function getBlogLabelById($localizationPluginProxyInterface, $blogId)
    {
        return vsprintf(
            '%s - %s',
            [
                $this->getBlogNameById($blogId),
                $localizationPluginProxyInterface->getBlogLocaleById($blogId),
            ]
        );
    }

    /**
     * Returns locale of current blog
     *
     * @param LocalizationPluginProxyInterface $localizationPlugin
     *
     * @return string
     */
    public function getCurrentBlogLocale(LocalizationPluginProxyInterface $localizationPlugin)
    {
        return $localizationPlugin->getBlogLocaleById($this->getCurrentBlogId());
    }

    public function getPostTypes()
    {
        return array_values(get_post_types('', 'names'));

    }

    public function getTermTypes()
    {
        global $wp_taxonomies;

        return array_keys($wp_taxonomies);
    }

    /**
     * If current Blog Id differs from $blogId -> changes blog to ensure that blog id is set to $blogId
     *
     * @param $blogId
     */
    public function resetBlog($blogId = null)
    {
        if (null === $blogId) {
            $blogId = $this->getInitialBlogId();
        }

        $currentStack = $GLOBALS['_wp_switched_stack'];

        if ((int)reset($currentStack) === $blogId) {
            $this->switchBlogId($blogId);
            $GLOBALS['_wp_switched_stack'] = [];
        }
    }

    /**
     * @return LoggerInterface
     */
    protected function getLogger()
    {
        return $this->logger;
    }

}
