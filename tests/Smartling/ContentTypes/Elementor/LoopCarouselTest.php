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
}
