<?php

namespace Smartling\Tests\Smartling\Helpers;

use PHPUnit\Framework\TestCase;
use Smartling\Base\SmartlingCore;
use Smartling\DbAl\WordpressContentEntities\PostEntityStd;
use Smartling\Extensions\Acf\AcfDynamicSupport;
use Smartling\Helpers\EntityHelper;
use Smartling\Helpers\EventParameters\AfterDeserializeContentEventParameters;
use Smartling\Helpers\RelativeLinkedAttachmentCoreHelper;
use Smartling\Helpers\TranslationHelper;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Tests\Mocks\WordpressFunctionsMockHelper;

class RelativeLinkedAttachmentCoreHelperTest extends TestCase
{
    protected function setUp()
    {
        WordpressFunctionsMockHelper::injectFunctionsMocks();
    }

    /**
     * @param string $string
     * @param array $definitions
     * @param int $sourceId
     * @param int $targetId
     * @dataProvider processGutenbergBlockAcfProvider
     */
    public function testProcessGutenbergBlockAcf($string, array $definitions, $sourceId, $targetId)
    {
        $batchUid = '';
        $sourceBlogId = 1;
        $targetBlogId = 2;
        $submission = new SubmissionEntity();
        $submission->setSourceBlogId($sourceBlogId);
        $submission->setSourceId($sourceId);
        $submission->setTargetId($targetId);
        $submission->setTargetBlogId($targetBlogId);
        $submission->setBatchUid($batchUid);
        $acf = $this->getMockBuilder(AcfDynamicSupport::class)
            ->setConstructorArgs([$this->getMock(EntityHelper::class)])
            ->getMock();
        $acf->method('getDefinitions')->willReturn($definitions);

        $translationHelper = $this->getMock(TranslationHelper::class);
        $translationHelper->method('isRelatedSubmissionCreationNeeded')->willReturn(true);

        $core = $this->getMock(SmartlingCore::class);
        $core->method('getTranslationHelper')->willReturn($translationHelper);
        $core->expects(self::once())->method('sendAttachmentForTranslation')
            ->with($sourceBlogId, $targetBlogId, $sourceId, $batchUid)
            ->willReturn($submission);
        $x = $this->getMockBuilder(RelativeLinkedAttachmentCoreHelper::class)->setConstructorArgs([
            $core,
            $acf
        ])->setMethods(null)->getMock();
        $source = [$string];
        $meta = [];

        $content = $this->getMock(PostEntityStd::class);

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
        $batchUid = '';
        $sourceBlogId = 1;
        $targetBlogId = 2;

        $submission = new SubmissionEntity();
        $submission->setSourceBlogId($sourceBlogId);
        $submission->setSourceId($sourceId);
        $submission->setTargetId($targetId);
        $submission->setTargetBlogId($targetBlogId);
        $submission->setBatchUid($batchUid);

        $acf = $this->getMockBuilder(AcfDynamicSupport::class)
            ->setConstructorArgs([$this->getMock(EntityHelper::class)])
            ->getMock();
        $acf->method('getDefinitions')->willReturn(
            [
                'field_5eb1344b55a84' => ['type' => 'image'],
                'field_5ef64590591dc' => ['type' => 'text']
            ]
        );

        $translationHelper = $this->getMock(TranslationHelper::class);
        $translationHelper->method('isRelatedSubmissionCreationNeeded')->willReturn(true);

        $core = $this->getMock(SmartlingCore::class);
        $core->method('getTranslationHelper')->willReturn($translationHelper);
        $core->expects(self::never())->method('sendAttachmentForTranslation');

        $x = $this->getMockBuilder(RelativeLinkedAttachmentCoreHelper::class)->setConstructorArgs([
            $core,
            $acf
        ])->setMethods(null)->getMock();

        $source = [$string];
        $meta = [];
        $content = $this->getMock(PostEntityStd::class);

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
}
