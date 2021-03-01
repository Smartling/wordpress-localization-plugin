<?php

namespace {
    if (!class_exists('WP_Term')) {
        class WP_Term
        {
            public $name;
            public $taxonomy;
            public $term_id;

            public function __construct($object)
            {
                $this->name = $object->name;
                $this->taxonomy = $object->taxonomy;
                $this->term_id = $object->term_id;
            }
        }
    }

    if (!function_exists('get_current_blog_id')) {
        function get_current_blog_id()
        {
            return 1;
        }
    }
}

namespace Smartling\Tests\Smartling\WP\Controller {

    use PHPUnit\Framework\TestCase;
    use Smartling\DbAl\LocalizationPluginProxyInterface;
    use Smartling\Helpers\Cache;
    use Smartling\Helpers\EntityHelper;
    use Smartling\Helpers\PluginInfo;
    use Smartling\Helpers\SiteHelper;
    use Smartling\Helpers\WordpressFunctionProxyHelper;
    use Smartling\Submissions\SubmissionEntity;
    use Smartling\Submissions\SubmissionManager;
    use Smartling\WP\Controller\TaxonomyLinksController;

    class TaxonomyLinksControllerTest extends TestCase
    {
        public function testGetTerms()
        {
            $x = new TaxonomyLinksController(
                $this->getMockBuilder(PluginInfo::class)->disableOriginalConstructor()->getMock(),
                $this->createMock(LocalizationPluginProxyInterface::class),
                $this->getSiteHelperMock(),
                $this->getSubmissionManagerMock(),
                $this->getWordpressMock(),
                $this->createMock(EntityHelper::class),
                $this->createMock(Cache::class)
            );

            self::assertEquals([
                 1 => [
                     'category' => [['value' => 1, 'label' => 'category 1'], ['value' => 2, 'label' => 'category 2']],
                     'post_tag' => [['value' => 3, 'label' => 'tag 1']],
                 ],
                 2 => ['category' => [['value' => 1, 'label' => 'cat~ego~ry 1'], ['value' => 2, 'label' => 'cat~ego~ry 2']]],
             ], $x->getTerms());
        }

        private function getSubmission($sourceId, $targetId)
        {
            $submission = new SubmissionEntity();
            $submission->setSourceId($sourceId);
            $submission->setTargetId($targetId);

            return $submission;
        }

        private function getSubmissionManagerMock($findResult = [])
        {
            $submissionManager = $this->getMockBuilder(SubmissionManager::class)->disableOriginalConstructor()->getMock();
            $submissionManager->method('find')->willReturn($findResult);

            return $submissionManager;
        }

        private function getTermObject($id, $name, $taxonomy = 'category')
        {
            $return = new \StdClass();
            $return->term_id = $id;
            $return->name = $name;
            $return->taxonomy = $taxonomy;

            return $return;
        }

        private function getWordpressMock()
        {
            $wordpress = $this->createMock(WordpressFunctionProxyHelper::class);
            $wordpress->method('get_current_blog_id')->willReturn(1);
            $wordpress->method('get_terms')->willReturnOnConsecutiveCalls([
                new \WP_Term($this->getTermObject(1, 'category 1')),
                new \WP_Term($this->getTermObject(2, 'category 2')),
                new \WP_Term($this->getTermObject(3, 'tag 1', 'post_tag')),
            ], [
                new \WP_Term($this->getTermObject(1, 'cat~ego~ry 1')),
                new \WP_Term($this->getTermObject(2, 'cat~ego~ry 2')),
            ], []
            );
            return $wordpress;
        }

        private function getSiteHelperMock()
        {
            $siteHelper = $this->createMock(SiteHelper::class);
            $siteHelper->method('listBlogs')->willReturn([1, 2]);
            return $siteHelper;
        }
    }
}
