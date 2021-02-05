<?php

namespace Smartling\Tests\Smartling\Helpers;

use PHPUnit\Framework\TestCase;
use Smartling\Base\SmartlingCore;
use Smartling\DbAl\WordpressContentEntities\PostEntityStd;
use Smartling\Extensions\Acf\AcfDynamicSupport;
use Smartling\Helpers\EntityHelper;
use Smartling\Helpers\EventParameters\AfterDeserializeContentEventParameters;
use Smartling\Helpers\GutenbergReplacementRule;
use Smartling\Helpers\RelativeLinkedAttachmentCoreHelper;
use Smartling\Helpers\TranslationHelper;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Tests\Mocks\WordpressFunctionsMockHelper;
use Smartling\Tuner\MediaAttachmentRulesManager;

class RelativeLinkedAttachmentCoreHelperTest extends TestCase
{
    private $mediaAttachmentRulesManager;

    protected function setUp()
    {
        WordpressFunctionsMockHelper::injectFunctionsMocks();
        $this->mediaAttachmentRulesManager = $this->getMock(MediaAttachmentRulesManager::class);
        $this->mediaAttachmentRulesManager->method('getGutenbergReplacementRules')->willReturn([
            new GutenbergReplacementRule('image', 'id'),
            new GutenbergReplacementRule('media-text', 'mediaId'),
        ]);
    }

    public function testProcessGutenbergBlock()
    {
        $batchUid = '';
        $sourceBlogId = 1;
        $sourceMediaId = 5;
        $targetBlogId = 2;
        $targetMediaId = 6;
        $submission = new SubmissionEntity();
        $submission->setSourceBlogId($sourceBlogId);
        $submission->setSourceId($sourceMediaId);
        $submission->setTargetId($targetMediaId);
        $submission->setTargetBlogId($targetBlogId);
        $submission->setBatchUid($batchUid);
        $acf = $this->getMockBuilder(AcfDynamicSupport::class)
            ->setConstructorArgs([$this->getMock(EntityHelper::class)])
            ->getMock();
        $acf->method('getDefinitions')->willReturn([]);

        $translationHelper = $this->getMock(TranslationHelper::class);
        $translationHelper->method('isRelatedSubmissionCreationNeeded')->willReturn(true);

        $core = $this->getMock(SmartlingCore::class);
        $core->method('getTranslationHelper')->willReturn($translationHelper);
        $core->method('sendAttachmentForTranslation')->willReturn($submission);

        $x = $this->getMockBuilder(RelativeLinkedAttachmentCoreHelper::class)->setConstructorArgs([
            $core,
            $acf,
            $this->mediaAttachmentRulesManager,
        ])->setMethods(null)->getMock();
        $source = [<<<HTML
<!-- wp:core/media-text {"mediaId":$sourceMediaId,"mediaLink":"http://test.com","mediaType":"image"} -->
<div class="wp-block-media-text alignwide is-stacked-on-mobile"><figure class="wp-block-media-text__media"><img src="http://example.com/image.jpg" alt="" class="wp-image-$sourceMediaId"/></figure><div class="wp-block-media-text__content"><!-- wp:core/paragraph {"placeholder":"Content…","fontSize":"large"} -->
<p class="has-large-font-size">Some text and wp-image-$sourceMediaId that should NOT be replaced</p>
<!-- /wp:core/paragraph --></div></div>
<!-- /wp:core/media-text -->

<!-- wp:core/image {"id":$sourceMediaId,"sizeSlug":"large"} -->
<figure class="wp-block-image size-large"><img src="http://example.com/image.jpg" alt="" class="wp-image-$sourceMediaId"/></figure>
<!-- /wp:core/image -->
HTML
        ];
        $x->processor(new AfterDeserializeContentEventParameters($source, $submission, $this->getMock(PostEntityStd::class), []));

        self::assertEquals(
            <<<HTML
<!-- wp:media-text {"mediaId":$targetMediaId,"mediaLink":"http:\/\/test.com","mediaType":"image"} -->
<div class="wp-block-media-text alignwide is-stacked-on-mobile"><figure class="wp-block-media-text__media"><img src="http://example.com/image.jpg" alt="" class="wp-image-$targetMediaId"/></figure><div class="wp-block-media-text__content"><!-- wp:core/paragraph {"placeholder":"Content…","fontSize":"large"} -->
<p class="has-large-font-size">Some text and wp-image-$sourceMediaId that should NOT be replaced</p>
<!-- /wp:core/paragraph --></div></div>
<!-- /wp:core/media-text -->

<!-- wp:image {"id":$targetMediaId,"sizeSlug":"large"} -->
<figure class="wp-block-image size-large"><img src="http://example.com/image.jpg" alt="" class="wp-image-$targetMediaId"/></figure>
<!-- /wp:core/image -->
HTML
            ,
            $source[0]
        );
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
            $acf,
            $this->mediaAttachmentRulesManager,
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
            $acf,
            $this->mediaAttachmentRulesManager
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
