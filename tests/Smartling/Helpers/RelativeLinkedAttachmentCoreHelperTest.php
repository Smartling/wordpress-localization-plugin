<?php

namespace Smartling\Tests\Smartling\Helpers;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Smartling\Base\SmartlingCore;
use Smartling\DbAl\WordpressContentEntities\PostEntityStd;
use Smartling\Extensions\Acf\AcfDynamicSupport;
use Smartling\Helpers\EntityHelper;
use Smartling\Helpers\EventParameters\AfterDeserializeContentEventParameters;
use Smartling\Helpers\RelativeLinkedAttachmentCoreHelper;
use Smartling\Helpers\TranslationHelper;
use Smartling\Jobs\JobEntity;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Tests\Mocks\WordpressFunctionsMockHelper;
use Smartling\Tuner\MediaAttachmentRulesManager;

class RelativeLinkedAttachmentCoreHelperTest extends TestCase
{
    private $mediaAttachmentRulesManager;

    protected function setUp(): void
    {
        WordpressFunctionsMockHelper::injectFunctionsMocks();
        $this->mediaAttachmentRulesManager = $this->createMock(MediaAttachmentRulesManager::class);
    }

    /**
     * @dataProvider processGutenbergBlockAcfProvider
     */
    public function testProcessGutenbergBlockAcf(string $string, array $definitions, int $sourceId, int $targetId)
    {
        $sourceBlogId = 1;
        $targetBlogId = 2;
        $submission = new SubmissionEntity();
        $submission->setContentType('content_type');
        $submission->setSourceBlogId($sourceBlogId);
        $submission->setSourceId($sourceId);
        $submission->setTargetId($targetId);
        $submission->setTargetBlogId($targetBlogId);
        $jobInfo = new JobEntity('', '', '', 1, new \DateTime('2000-12-24 01:23:46'), new \DateTime('2001-12-24 02:34:45'));
        $submission->setBatchUid('');
        $submission->setJobInfo($jobInfo);
        $acf = $this->getMockBuilder(AcfDynamicSupport::class)
            ->setConstructorArgs([$this->createMock(EntityHelper::class)])
            ->getMock();
        $acf->method('getDefinitions')->willReturn($definitions);

        $translationHelper = $this->createMock(TranslationHelper::class);
        $translationHelper->method('isRelatedSubmissionCreationNeeded')->willReturn(true);

        $core = $this->getCoreMock();
        $core->method('getTranslationHelper')->willReturn($translationHelper);
        $core->expects(self::once())->method('sendAttachmentForTranslation')
            ->with($sourceBlogId, $targetBlogId, $sourceId)
            ->willReturn($submission);
        $x = $this->getMockBuilder(RelativeLinkedAttachmentCoreHelper::class)->setConstructorArgs([
            $core,
            $acf,
            $this->mediaAttachmentRulesManager,
        ])->onlyMethods([])->getMock();
        $source = [$string];
        $meta = [];

        $content = $this->createMock(PostEntityStd::class);

        $x->processor(new AfterDeserializeContentEventParameters($source, $submission, $content, $meta));

        self::assertEquals(
            str_replace($sourceId, $targetId, $string),
            $source[0]
        );
    }

    /**
     * Attachment id can be an empty string.
     *
     * Connector must not throw `Source id can not be 0` exception in this case.
     */
    public function testProcessGutenbergBlockAcfWithAttachmentWithEmptyId()
    {
        $string = '<!-- wp:acf/testimonial {\"id\":\"block_5f1eb3f391cda\",\"name\":\"acf/testimonial\",\"data\":{\"media\":\"\",\"_media\":\"field_5eb1344b55a84\",\"description\":\"text\",\"_description\":\"field_5ef64590591dc\"},\"align\":\"\",\"mode\":\"edit\"} /-->';
        $sourceId = 0;
        $targetId = 262;
        $sourceBlogId = 1;
        $targetBlogId = 2;

        $submission = new SubmissionEntity();
        $submission->setSourceBlogId($sourceBlogId);
        $submission->setSourceId($sourceId);
        $submission->setTargetId($targetId);
        $submission->setTargetBlogId($targetBlogId);
        $submission->setBatchUid('');

        $acf = $this->getMockBuilder(AcfDynamicSupport::class)
            ->setConstructorArgs([$this->createMock(EntityHelper::class)])
            ->getMock();
        $acf->method('getDefinitions')->willReturn(
            [
                'field_5eb1344b55a84' => ['type' => 'image'],
                'field_5ef64590591dc' => ['type' => 'text']
            ]
        );

        $translationHelper = $this->createMock(TranslationHelper::class);
        $translationHelper->method('isRelatedSubmissionCreationNeeded')->willReturn(true);

        $core = $this->getCoreMock();
        $core->method('getTranslationHelper')->willReturn($translationHelper);
        $core->expects(self::never())->method('sendAttachmentForTranslation');

        $x = $this->getMockBuilder(RelativeLinkedAttachmentCoreHelper::class)->setConstructorArgs([
            $core,
            $acf,
            $this->mediaAttachmentRulesManager
        ])->onlyMethods([])->getMock();

        $source = [$string];
        $meta = [];
        $content = $this->createMock(PostEntityStd::class);

        $x->processor(new AfterDeserializeContentEventParameters($source, $submission, $content, $meta));
    }

    /**
     * @return array
     */
    public function processGutenbergBlockAcfProvider()
    {
        return [
            [
                '<!-- wp:acf/testimonial {\"id\":\"block_5f1eb3f391cda\",\"name\":\"acf/testimonial\",\"data\":' .
                '{\"media\":\"297\",\"_media\":\"field_5eb1344b55a84\",\"description\":\"text\",\"_description\":' .
                '\"field_5ef64590591dc\"},\"align\":\"\",\"mode\":\"edit\"} /-->',
                ['field_5eb1344b55a84' => ['type' => 'image'], 'field_5ef64590591dc' => ['type' => 'text']],
                297,
                262,
            ],
        ];
    }

    /**
     * @return MockObject|SmartlingCore
     */
    private function getCoreMock()
    {
        return $this->createMock(SmartlingCore::class);
    }
}
