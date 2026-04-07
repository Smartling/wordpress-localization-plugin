<?php

namespace Smartling\ContentTypes\Elementor;

use PHPUnit\Framework\TestCase;
use Smartling\ContentTypes\ContentTypeHelper;
use Smartling\ContentTypes\Elementor\Elements\Gallery;
use Smartling\ContentTypes\ExternalContentElementor;
use Smartling\Models\Content;
use Smartling\Models\RelatedContentInfo;
use Smartling\Submissions\SubmissionEntity;

class GalleryTest extends TestCase
{
    private function makeWidget(array $settings = []): Gallery
    {
        return new Gallery([
            'id' => '14d5abc',
            'elType' => 'widget',
            'widgetType' => 'gallery',
            'settings' => $settings,
            'elements' => [],
        ]);
    }

    public function testRelated(): void
    {
        $imageSourceId = 21162;
        $imageTargetId = 31162;

        $externalContentElementor = $this->createMock(ExternalContentElementor::class);
        $externalContentElementor->method('getTargetId')
            ->with(0, $imageSourceId, 0, ContentTypeHelper::POST_TYPE_ATTACHMENT)->willReturn($imageTargetId);

        $this->assertEquals(
            $imageTargetId,
            $this->makeWidget(['gallery' => [
                ['id' => 21161, 'url' => 'https://example.com/1.webp'],
                ['id' => $imageSourceId, 'url' => 'https://example.com/2.webp'],
                ['id' => 21163, 'url' => 'https://example.com/3.webp'],
            ]])
                ->setRelations(
                    new Content($imageSourceId, ContentTypeHelper::POST_TYPE_ATTACHMENT),
                    $externalContentElementor,
                    'settings/gallery/1/id',
                    $this->createMock(SubmissionEntity::class),
                )->toArray()['settings']['gallery'][1]['id'],
        );
    }

    public function testGetTranslatableStrings(): void
    {
        $strings = $this->makeWidget(['galleries' => [
            ['gallery_title' => 'New Gallery', '_id' => '04c68ec'],
            ['gallery_title' => 'Second Gallery', '_id' => 'ab12345'],
        ]])->getTranslatableStrings();

        $this->assertEquals('New Gallery', $strings['14d5abc']['galleries/04c68ec']['gallery_title']);
        $this->assertEquals('Second Gallery', $strings['14d5abc']['galleries/ab12345']['gallery_title']);
    }

    public function testSetTargetContent(): void
    {
        $result = $this->makeWidget(['galleries' => [
            ['gallery_title' => 'New Gallery', '_id' => '04c68ec'],
            ['gallery_title' => 'Second Gallery', '_id' => 'ab12345'],
        ]])->setTargetContent(
            $this->createMock(ExternalContentElementor::class),
            new RelatedContentInfo([]),
            [
                '14d5abc' => [
                    'galleries' => [
                        '04c68ec' => ['gallery_title' => 'Translated Gallery'],
                        'ab12345' => ['gallery_title' => 'Translated Second'],
                    ],
                ],
            ],
            $this->createMock(SubmissionEntity::class),
        )->toArray();

        $this->assertEquals('Translated Gallery', $result['settings']['galleries'][0]['gallery_title']);
        $this->assertEquals('Translated Second', $result['settings']['galleries'][1]['gallery_title']);
    }
}
