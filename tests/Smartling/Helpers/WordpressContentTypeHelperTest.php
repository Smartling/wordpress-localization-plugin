<?php

namespace Smartling\Helpers {
    // Bare, unproxied WP global called by WordpressContentTypeHelper::buildEditUrl().
    // Not stubbed anywhere else for the unit test suite.
    if (!function_exists(__NAMESPACE__ . '\\get_admin_url')) {
        function get_admin_url($blogId = null, $path = '')
        {
            return "https://blog-{$blogId}.test{$path}";
        }
    }
}

namespace Smartling\Tests\Smartling\Helpers {

    use PHPUnit\Framework\TestCase;
    use Smartling\Bootstrap;
    use Smartling\ContentTypes\ContentTypeAbstract;
    use Smartling\ContentTypes\ContentTypeInterface;
    use Smartling\ContentTypes\ContentTypeManager;
    use Smartling\Exception\SmartlingInvalidFactoryArgumentException;
    use Smartling\Helpers\WordpressContentTypeHelper;
    use Smartling\Submissions\SubmissionEntity;

    class WordpressContentTypeHelperTest extends TestCase
    {
        private object $originalManager;

        protected function setUp(): void
        {
            parent::setUp();
            $this->originalManager = Bootstrap::getContainer()->get('content-type-descriptor-manager');
        }

        protected function tearDown(): void
        {
            Bootstrap::getContainer()->set('content-type-descriptor-manager', $this->originalManager);
            parent::tearDown();
        }

        private function registerContentTypeHandler(?string $baseType): void
        {
            $manager = $this->createMock(ContentTypeManager::class);

            if ($baseType === null) {
                // A handler that implements the interface but not ContentTypeAbstract,
                // matching the "unknown/unregistered content type" branch in production code.
                $manager->method('getHandler')->willReturn($this->createMock(ContentTypeInterface::class));
            } else {
                $handler = $this->createMock(ContentTypeAbstract::class);
                $handler->method('getBaseType')->willReturn($baseType);
                $manager->method('getHandler')->willReturn($handler);
            }

            Bootstrap::getContainer()->set('content-type-descriptor-manager', $manager);
        }

        /**
         * ContentTypeManager::getHandler() throws (rather than returning some sentinel
         * value) when the requested content type was never registered as a descriptor -
         * e.g. a historical submission row whose custom-post-type integration has since
         * been removed/deactivated. buildEditUrl() must not let that exception escape.
         */
        private function registerThrowingContentTypeManager(): void
        {
            $manager = $this->createMock(ContentTypeManager::class);
            $manager->method('getHandler')->willThrowException(
                new SmartlingInvalidFactoryArgumentException("Requested descriptor for 'sovos_product' that doesn't exists.")
            );

            Bootstrap::getContainer()->set('content-type-descriptor-manager', $manager);
        }

        public function testGetSourceEditUrlReturnsEmptyStringWhenContentTypeManagerThrows(): void
        {
            $this->registerThrowingContentTypeManager();

            $submission = (new SubmissionEntity())
                ->setContentType('sovos_product')
                ->setSourceBlogId(1)
                ->setSourceId(1);

            $this->assertSame('', WordpressContentTypeHelper::getSourceEditUrl($submission));
        }

        public function testGetEditUrlReturnsEmptyStringWhenContentTypeManagerThrows(): void
        {
            $this->registerThrowingContentTypeManager();

            $submission = (new SubmissionEntity())
                ->setContentType('sovos_product')
                ->setTargetBlogId(1)
                ->setTargetId(1);

            $this->assertSame('', WordpressContentTypeHelper::getEditUrl($submission));
        }

        public function testGetSourceEditUrlBuildsPostEditLink(): void
        {
            $this->registerContentTypeHandler('post');

            $submission = (new SubmissionEntity())
                ->setContentType('post')
                ->setSourceBlogId(5)
                ->setSourceId(42);

            $this->assertSame(
                'https://blog-5.test/post.php?post=42&action=edit',
                WordpressContentTypeHelper::getSourceEditUrl($submission)
            );
        }

        public function testGetSourceEditUrlBuildsAttachmentEditLinkUsingPostPhp(): void
        {
            // Attachments share the 'post' base type, so they must resolve through the
            // same post.php edit screen as regular posts, not upload.php.
            $this->registerContentTypeHandler('post');

            $submission = (new SubmissionEntity())
                ->setContentType('attachment')
                ->setSourceBlogId(5)
                ->setSourceId(99);

            $this->assertSame(
                'https://blog-5.test/post.php?post=99&action=edit',
                WordpressContentTypeHelper::getSourceEditUrl($submission)
            );
        }

        public function testGetSourceEditUrlBuildsTaxonomyEditLink(): void
        {
            $this->registerContentTypeHandler('taxonomy');

            $submission = (new SubmissionEntity())
                ->setContentType('category')
                ->setSourceBlogId(7)
                ->setSourceId(13);

            $this->assertSame(
                'https://blog-7.test/term.php?taxonomy=category&tag_ID=13',
                WordpressContentTypeHelper::getSourceEditUrl($submission)
            );
        }

        public function testGetSourceEditUrlReturnsEmptyStringForUnsupportedBaseType(): void
        {
            $this->registerContentTypeHandler('virtual');

            $submission = (new SubmissionEntity())
                ->setContentType('menu')
                ->setSourceBlogId(1)
                ->setSourceId(1);

            $this->assertSame('', WordpressContentTypeHelper::getSourceEditUrl($submission));
        }

        public function testGetSourceEditUrlReturnsEmptyStringForUnknownContentType(): void
        {
            $this->registerContentTypeHandler(null);

            $submission = (new SubmissionEntity())
                ->setContentType('does-not-exist')
                ->setSourceBlogId(1)
                ->setSourceId(1);

            $this->assertSame('', WordpressContentTypeHelper::getSourceEditUrl($submission));
        }

        public function testGetEditUrlStillBuildsTargetEditLink(): void
        {
            // Regression check: refactoring getEditUrl() to share code with
            // getSourceEditUrl() must not change its existing target-link behavior.
            $this->registerContentTypeHandler('post');

            $submission = (new SubmissionEntity())
                ->setContentType('post')
                ->setTargetBlogId(9)
                ->setTargetId(101);

            $this->assertSame(
                'https://blog-9.test/post.php?post=101&action=edit',
                WordpressContentTypeHelper::getEditUrl($submission)
            );
        }
    }
}
