<?php

namespace Smartling\ContentTypes\Elementor;

use PHPUnit\Framework\TestCase;
use Smartling\ContentTypes\ContentTypeHelper;
use Smartling\ContentTypes\Elementor\Elements\ImageGallery;
use Smartling\ContentTypes\ExternalContentElementor;
use Smartling\Models\Content;
use Smartling\Submissions\SubmissionEntity;

class ImageGalleryTest extends TestCase
{
    public function testRelated(): void
    {
        $imageSourceId = 7;
        $imageTargetId = 11;

        $externalContentElementor = $this->createMock(ExternalContentElementor::class);
        $externalContentElementor->method('getTargetId')
            ->with(0, $imageSourceId, 0, ContentTypeHelper::POST_TYPE_ATTACHMENT)->willReturn($imageTargetId);

        $this->assertEquals(
            $imageTargetId,
            (new ImageGallery(['settings' => ['wp_gallery' => [['id' => 5], ['id' => $imageSourceId], ['id' => 9]]]]))
                ->setRelations(
                    new Content($imageSourceId, ContentTypeHelper::POST_TYPE_ATTACHMENT),
                    $externalContentElementor,
                    'settings/wp_gallery/1/id',
                    $this->createMock(SubmissionEntity::class),
                )->toArray()['settings']['wp_gallery'][1]['id'],
        );
    }
}
