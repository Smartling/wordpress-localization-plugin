<?php

namespace Smartling\ContentTypes\Elementor;

use PHPUnit\Framework\TestCase;
use Smartling\ContentTypes\ContentTypeHelper;
use Smartling\ContentTypes\Elementor\Elements\LoopCarousel;
use Smartling\ContentTypes\ExternalContentElementor;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Models\Content;
use Smartling\Submissions\SubmissionEntity;

class LoopCarouselTest extends TestCase
{
    public function testTemplateIdType(): void
    {
        $templateSourceId = 123;
        $templateTargetId = 456;

        $proxy = $this->createMock(WordpressFunctionProxyHelper::class);
        $proxy->expects($this->once())->method('get_post_type')->with($templateSourceId)->willReturn('post');

        $externalContentElementor = $this->createMock(ExternalContentElementor::class);
        $externalContentElementor->method('getTargetId')->willReturn($templateTargetId);
        $externalContentElementor->method('getWpProxy')->willReturn($proxy);

        $submission = $this->createMock(SubmissionEntity::class);
        $submission->method('getContentType')->willReturn('post');
        $submission->method('getTargetId')->willReturn(2);

        $this->assertEquals(
            $templateTargetId,
            (new LoopCarousel(['settings' => ['template_id' => (string)$templateSourceId]]))->setRelations(
                new Content($templateSourceId, ContentTypeHelper::CONTENT_TYPE_POST),
                $externalContentElementor,
                'settings/template_id',
                $submission,
            )->toArray()['settings']['template_id'],
        );
    }

    public function testTermIdRelatedContent(): void
    {
        $relatedList = (new LoopCarousel([
            'settings' => [
                'post_query_include_term_ids' => ['14', '15', '16']
            ]
        ]))->getRelated()->getRelatedContentList();

        $this->assertArrayHasKey(ContentTypeHelper::CONTENT_TYPE_TAXONOMY, $relatedList);
        $this->assertEquals(['14', '15', '16'], $relatedList[ContentTypeHelper::CONTENT_TYPE_TAXONOMY]);
    }

    public function testTermIdTranslation(): void
    {
        $termSourceId = 14;
        $termTargetId = 28;

        $externalContentElementor = $this->createMock(ExternalContentElementor::class);
        $externalContentElementor->method('getTargetId')
            ->with(0, $termSourceId, 0, ContentTypeHelper::CONTENT_TYPE_TAXONOMY)->willReturn($termTargetId);

        $this->assertEquals(
            $termTargetId,
            (new LoopCarousel([
                'settings' => [
                    'post_query_include_term_ids' => ['14', '15', '16']
                ]
            ]))->setRelations(
                new Content($termSourceId, ContentTypeHelper::CONTENT_TYPE_TAXONOMY),
                $externalContentElementor,
                'settings/post_query_include_term_ids/0',
                $this->createMock(SubmissionEntity::class),
            )->toArray()['settings']['post_query_include_term_ids'][0],
        );
    }
}
