<?php

namespace Smartling\Tests\Smartling\Helpers;

use PHPUnit\Framework\TestCase;
use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\Exception\BlogNotFoundException;
use Smartling\Helpers\SiteHelper;
use Smartling\Tests\Mocks\WordpressFunctionsMockHelper;

class SiteHelperTest extends TestCase
{
    protected function setUp(): void
    {
        WordpressFunctionsMockHelper::injectFunctionsMocks();
        CountingSiteHelper::resetLabelCache();
    }

    protected function tearDown(): void
    {
        CountingSiteHelper::resetLabelCache();
    }

    /**
     * Every profile load resolves its blog labels, and resolving a label switches blogs (WP-1025).
     */
    public function testBlogLabelIsResolvedOncePerBlog(): void
    {
        $helper = new CountingSiteHelper(['2' => 'Spanish Site - es_ES', '3' => 'French Site - fr_FR']);
        $proxy = $this->createMock(LocalizationPluginProxyInterface::class);

        for ($i = 0; $i < 5; $i++) {
            self::assertSame('Spanish Site - es_ES', $helper->getBlogLabelById($proxy, 2));
            self::assertSame('French Site - fr_FR', $helper->getBlogLabelById($proxy, 3));
        }

        self::assertSame([2 => 1, 3 => 1], $helper->resolved);
    }

    public function testBlogLabelCacheIsSharedBetweenInstances(): void
    {
        $proxy = $this->createMock(LocalizationPluginProxyInterface::class);
        $first = new CountingSiteHelper(['2' => 'Spanish Site']);
        $second = new CountingSiteHelper(['2' => 'Spanish Site']);

        $first->getBlogLabelById($proxy, 2);
        $second->getBlogLabelById($proxy, 2);

        self::assertSame([2 => 1], $first->resolved);
        self::assertSame([], $second->resolved);
    }

    /**
     * SettingsManager::updateLabels() removes deleted blogs from profiles when the lookup throws,
     * so a missing blog must keep throwing instead of being cached.
     */
    public function testMissingBlogIsNotCached(): void
    {
        $helper = new CountingSiteHelper([]);
        $proxy = $this->createMock(LocalizationPluginProxyInterface::class);

        for ($i = 0; $i < 2; $i++) {
            try {
                $helper->getBlogLabelById($proxy, 7);
                self::fail('Expected BlogNotFoundException');
            } catch (BlogNotFoundException) {
            }
        }

        self::assertSame([7 => 2], $helper->resolved);
    }
}

class CountingSiteHelper extends SiteHelper
{
    /** @var int[] blog id => number of resolveBlogLabel() calls */
    public array $resolved = [];

    public function __construct(private array $labels)
    {
        parent::__construct();
    }

    public static function resetLabelCache(): void
    {
        static::$blogLabelCache = [];
    }

    protected function resolveBlogLabel(LocalizationPluginProxyInterface $localizationPluginProxyInterface, int $blogId): string
    {
        $this->resolved[$blogId] = ($this->resolved[$blogId] ?? 0) + 1;
        if (!array_key_exists((string)$blogId, $this->labels)) {
            throw new BlogNotFoundException("Blog $blogId not found");
        }

        return $this->labels[(string)$blogId];
    }
}
