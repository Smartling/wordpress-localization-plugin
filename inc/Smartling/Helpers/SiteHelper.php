<?php

namespace Smartling\Helpers;

use InvalidArgumentException;
use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\Exception\BlogNotFoundException;
use Smartling\Exception\SmartlingDirectRunRuntimeException;
use Smartling\MonologWrapper\MonologWrapper;
use Smartling\Vendor\Psr\Log\LoggerInterface;

class SiteHelper
{
    private LoggerInterface $logger;
    private int $initialBlogId;

    public function getInitialBlogId(): int
    {
        return $this->initialBlogId;
    }

    public function __construct()
    {
        $this->logger = MonologWrapper::getLogger(get_called_class());

        $this->initialBlogId = $this->getCurrentBlogId();
    }

    /**
     * @var int[][]
     */
    protected static array $_siteCache = [];

    /**
     * @var int[]
     */
    protected static array $_flatBlogIdCache = [];

    /**
     * Fallback for direct run if WordPress functionality is not reachable
     * @throws SmartlingDirectRunRuntimeException
     */
    private function directRunDetectedFallback(string $requiredFunction = ''): void
    {
        $message = 'Direct run detected. Required run as Wordpress plugin.';
        if ($requiredFunction !== '') {
            $message = "Required WordPress function ($requiredFunction) was not loaded.";
        }

        $this->getLogger()->error($message);

        throw new SmartlingDirectRunRuntimeException($message);
    }

    /**
     * @throws SmartlingDirectRunRuntimeException
     */
    private function cacheSites(): void
    {
        if (!function_exists('get_sites')) {
            $this->directRunDetectedFallback('get_sites');
        }
        if (empty(static::$_siteCache)) {
            $sites = get_sites(['number' => 1000]);
            foreach ($sites as $site) {

                static::$_siteCache[$site->site_id][] = (int)$site->blog_id;
                static::$_flatBlogIdCache[] = (int)$site->blog_id;
            }
        }
    }

    /**
     * @return int[]
     * @throws SmartlingDirectRunRuntimeException
     * @throws InvalidArgumentException
     */
    public function listBlogs(int $siteId = 1): array
    {
        $this->cacheSites();

        if (isset(static::$_siteCache[$siteId])) {
            return static::$_siteCache[$siteId];
        }

        $message = 'Invalid site_id value set.';
        throw new \InvalidArgumentException($message);
    }

    public function listBlogIdsFlat(): array
    {
        $this->cacheSites();

        return static::$_flatBlogIdCache;
    }

    /**
     * @throws SmartlingDirectRunRuntimeException
     */
    public function getCurrentSiteId(): int
    {
        if (function_exists('get_current_site')) {
            return get_current_site()->id;
        }

        $this->directRunDetectedFallback();
    }

    /**
     * @throws SmartlingDirectRunRuntimeException
     */
    public function getCurrentBlogId(): int
    {
        if (function_exists('get_current_blog_id')) {
            return get_current_blog_id();
        }

        $this->directRunDetectedFallback();
    }

    /**
     * @throws BlogNotFoundException
     * @throws SmartlingDirectRunRuntimeException
     */
    public function switchBlogId(int $blogId): void
    {
        $this->cacheSites();

        if (!in_array($blogId, static::$_flatBlogIdCache, true)) {
            $message = vsprintf('Invalid blogId value. Got %s, expected one of [%s]',
                                [$blogId, implode(',', static::$_flatBlogIdCache)]);

            throw new BlogNotFoundException($message);
        }

        if (function_exists('switch_to_blog')) {
            switch_to_blog($blogId);
        } else {
            $this->directRunDetectedFallback();
        }
    }

    public function restoreBlogId(): void
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
     * @return mixed function result
     * @throws BlogNotFoundException
     * @throws SmartlingDirectRunRuntimeException
     */
    public function withBlog(int $blogId, callable $function)
    {
        $this->switchBlogId($blogId);
        try {
            return $function();
        } finally {
            $this->restoreBlogId();
        }
    }

    /**
     * @throws BlogNotFoundException
     */
    private function getBlogNameById(int $blogId): string
    {
        $this->switchBlogId($blogId);
        $label = get_bloginfo('Name');
        $this->restoreBlogId();

        return $label;
    }

    /**
     * @throws BlogNotFoundException
     */
    public function getBlogLabelById(LocalizationPluginProxyInterface $localizationPluginProxyInterface, int $blogId): string
    {
        $blogName = $this->getBlogNameById($blogId);
        $locale = $localizationPluginProxyInterface->getBlogLocaleById($blogId);

        return StringHelper::isNullOrEmpty($locale) ? $blogName : "$blogName - $locale";
    }

    public function getCurrentBlogLocale(LocalizationPluginProxyInterface $localizationPlugin): string
    {
        return $localizationPlugin->getBlogLocaleById($this->getCurrentBlogId());
    }

    public function getPostTypes(): array
    {
        return array_values(get_post_types('', 'names'));

    }

    public function getTermTypes(): array
    {
        global $wp_taxonomies;

        return array_keys($wp_taxonomies);
    }

    /**
     * If current Blog Id differs from $blogId -> changes blog to ensure that blog id is set to $blogId
     */
    public function resetBlog(?int $blogId = null): void
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

    protected function getLogger(): LoggerInterface
    {
        return $this->logger;
    }
}
