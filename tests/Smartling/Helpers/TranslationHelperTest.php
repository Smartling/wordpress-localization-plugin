<?php

namespace {
    public function get_site_option() {

    }
}

namespace Smartling\Tests\Smartling\Helpers {

    use PHPUnit\Framework\TestCase;
    use Smartling\Helpers\TranslationHelper;
    use Smartling\Submissions\SubmissionManager;

    class TranslationHelperTest extends TestCase
    {
        public function testIsRelatedSubmissionCreationNeeded()
        {
            $x = new TranslationHelper();
            $submissionManager = $this->getMockBuilder(SubmissionManager::class)->disableOriginalConstructor()->getMock();
            $submissionManager->method('submissionExistsNoLastError')->willReturn([true, false]);
            $x->setSubmissionManager($submissionManager);
            self::assertTrue($x->isRelatedSubmissionCreationNeeded('test', 1, 2, 3));
            self::assertFalse($x->isRelatedSubmissionCreationNeeded('test', 1, 2, 3));
        }
    }
}
