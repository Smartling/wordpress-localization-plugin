<?php

namespace Smartling\ContentTypes\Elementor;

use PHPUnit\Framework\TestCase;
use Smartling\ContentTypes\ContentTypeHelper;
use Smartling\ContentTypes\Elementor\Elements\NestedAccordion;
use Smartling\ContentTypes\ExternalContentElementor;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Models\Content;
use Smartling\Models\RelatedContentInfo;
use Smartling\Submissions\SubmissionEntity;

class NestedAccordionTest extends TestCase
{
    private function makeWidget(array $settings = []): NestedAccordion
    {
        return new NestedAccordion([
            'id' => 'cf8c5a0',
            'elType' => 'widget',
            'widgetType' => 'nested-accordion',
            'settings' => $settings,
            'elements' => [],
        ]);
    }

    public function testGetRelatedReturnsBothIconIds(): void
    {
        $widget = $this->makeWidget([
            'accordion_item_title_icon' => ['value' => ['url' => 'https://example.com/plus.svg', 'id' => 329], 'library' => 'svg'],
            'accordion_item_title_icon_active' => ['value' => ['url' => 'https://example.com/minus.svg', 'id' => 330], 'library' => 'svg'],
        ]);

        $related = $widget->getRelated();
        $list = $related->getRelatedContentList();

        $this->assertArrayHasKey(ContentTypeHelper::POST_TYPE_ATTACHMENT, $list);
        $this->assertContains(329, $list[ContentTypeHelper::POST_TYPE_ATTACHMENT]);
        $this->assertContains(330, $list[ContentTypeHelper::POST_TYPE_ATTACHMENT]);
    }

    public function testGetRelatedSkipsNonSvgIcons(): void
    {
        $widget = $this->makeWidget([
            'accordion_item_title_icon' => ['value' => 'fas fa-plus', 'library' => 'fa-solid'],
        ]);

        $related = $widget->getRelated();
        $list = $related->getRelatedContentList();

        $this->assertArrayNotHasKey(ContentTypeHelper::POST_TYPE_ATTACHMENT, $list);
    }

    public function testSetRelationsUpdatesIconId(): void
    {
        $sourceId = 329;
        $targetId = 500;

        $proxy = $this->createMock(WordpressFunctionProxyHelper::class);
        $externalContentElementor = $this->createMock(ExternalContentElementor::class);
        $externalContentElementor->method('getTargetId')->willReturn($targetId);
        $externalContentElementor->method('getWpProxy')->willReturn($proxy);

        $result = $this->makeWidget([
            'accordion_item_title_icon' => ['value' => ['url' => 'https://example.com/plus.svg', 'id' => $sourceId], 'library' => 'svg'],
        ])->setRelations(
            new Content($sourceId, ContentTypeHelper::POST_TYPE_ATTACHMENT),
            $externalContentElementor,
            'settings/accordion_item_title_icon/value/id',
            $this->createMock(SubmissionEntity::class),
        )->toArray();

        $this->assertEquals($targetId, $result['settings']['accordion_item_title_icon']['value']['id']);
    }

    public function testGetTranslatableStringsReturnsItemTitles(): void
    {
        $widget = $this->makeWidget([
            'items' => [
                ['_id' => 'aaa111', 'item_title' => 'First Item'],
                ['_id' => 'bbb222', 'item_title' => 'Second Item'],
            ],
        ]);

        $strings = $widget->getTranslatableStrings();

        $this->assertArrayHasKey('cf8c5a0', $strings);
        $this->assertEquals('First Item', $strings['cf8c5a0']['items/aaa111']['item_title']);
        $this->assertEquals('Second Item', $strings['cf8c5a0']['items/bbb222']['item_title']);
    }

    public function testSetTargetContentAppliesTranslatedItemTitles(): void
    {
        $proxy = $this->createMock(WordpressFunctionProxyHelper::class);
        $externalContentElementor = $this->createMock(ExternalContentElementor::class);
        $externalContentElementor->method('getWpProxy')->willReturn($proxy);

        $widget = $this->makeWidget([
            'items' => [
                ['_id' => 'aaa111', 'item_title' => 'First Item'],
                ['_id' => 'bbb222', 'item_title' => 'Second Item'],
            ],
        ]);

        $strings = [
            'cf8c5a0' => [
                'items' => [
                    'aaa111' => ['item_title' => 'Translated First'],
                    'bbb222' => ['item_title' => 'Translated Second'],
                ],
            ],
        ];

        $result = $widget->setTargetContent(
            $externalContentElementor,
            new RelatedContentInfo([]),
            $strings,
            $this->createMock(SubmissionEntity::class),
        )->toArray();

        $this->assertEquals('Translated First', $result['settings']['items'][0]['item_title']);
        $this->assertEquals('Translated Second', $result['settings']['items'][1]['item_title']);
    }

    public function testSetTargetContentAppliesIconIdTranslation(): void
    {
        $sourceId = 329;
        $targetId = 500;

        $proxy = $this->createMock(WordpressFunctionProxyHelper::class);
        $externalContentElementor = $this->createMock(ExternalContentElementor::class);
        $externalContentElementor->method('getTargetId')
            ->with(0, $sourceId, 0, ContentTypeHelper::POST_TYPE_ATTACHMENT)
            ->willReturn($targetId);
        $externalContentElementor->method('getWpProxy')->willReturn($proxy);

        $submission = $this->createMock(SubmissionEntity::class);
        $submission->method('getSourceBlogId')->willReturn(0);
        $submission->method('getTargetBlogId')->willReturn(0);

        $widget = $this->makeWidget([
            'accordion_item_title_icon' => ['value' => ['url' => 'https://example.com/plus.svg', 'id' => $sourceId], 'library' => 'svg'],
            'items' => [['_id' => 'aaa111', 'item_title' => 'Title']],
        ]);

        $info = $widget->getRelated();

        $result = $widget->setTargetContent(
            $externalContentElementor,
            $info,
            [],
            $submission,
        )->toArray();

        $this->assertEquals($targetId, $result['settings']['accordion_item_title_icon']['value']['id']);
    }
}
