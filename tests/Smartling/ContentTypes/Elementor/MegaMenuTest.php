<?php

namespace Smartling\ContentTypes\Elementor;

use PHPUnit\Framework\TestCase;
use Smartling\ContentTypes\ContentTypeHelper;
use Smartling\ContentTypes\Elementor\Elements\MegaMenu;
use Smartling\ContentTypes\ExternalContentElementor;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Models\Content;
use Smartling\Models\RelatedContentInfo;
use Smartling\Submissions\SubmissionEntity;

class MegaMenuTest extends TestCase
{
    private function makeWidget(array $settings = []): MegaMenu
    {
        return new MegaMenu([
            'id' => '7399cf4',
            'elType' => 'widget',
            'widgetType' => 'mega-menu',
            'settings' => $settings,
            'elements' => [],
        ]);
    }

    public function testGetType(): void
    {
        $this->assertEquals('mega-menu', $this->makeWidget()->getType());
    }

    public function testGetRelatedReturnsSvgIconIds(): void
    {
        $widget = $this->makeWidget([
            'menu_item_icon' => ['value' => ['url' => 'https://example.com/right.svg', 'id' => 21340], 'library' => 'svg'],
            'menu_toggle_icon_normal' => ['value' => ['url' => 'https://example.com/menu.svg', 'id' => 1203], 'library' => 'svg'],
            'menu_toggle_icon_active' => ['value' => ['url' => 'https://example.com/close.svg', 'id' => 21246], 'library' => 'svg'],
        ]);

        $list = $widget->getRelated()->getRelatedContentList();

        $this->assertArrayHasKey(ContentTypeHelper::POST_TYPE_ATTACHMENT, $list);
        $this->assertContains(21340, $list[ContentTypeHelper::POST_TYPE_ATTACHMENT]);
        $this->assertContains(1203, $list[ContentTypeHelper::POST_TYPE_ATTACHMENT]);
        $this->assertContains(21246, $list[ContentTypeHelper::POST_TYPE_ATTACHMENT]);
    }

    public function testGetRelatedSkipsEmptyIconId(): void
    {
        $widget = $this->makeWidget([
            'menu_item_icon_active' => ['value' => '', 'library' => ''],
        ]);

        $list = $widget->getRelated()->getRelatedContentList();

        $this->assertArrayNotHasKey(ContentTypeHelper::POST_TYPE_ATTACHMENT, $list);
    }

    public function testGetRelatedExtractsDynamicTagFromMenuItem(): void
    {
        $widget = $this->makeWidget([
            'menu_items' => [
                [
                    '_id' => '1dc9acb',
                    'item_title' => 'Customers',
                    '__dynamic__' => [
                        'item_link' => '[elementor-tag id="70af237" name="internal-url" settings="%7B%22type%22%3A%22post%22%2C%22post_id%22%3A%228603%22%7D"]',
                    ],
                ],
            ],
        ]);

        $list = $widget->getRelated()->getRelatedContentList();

        $this->assertArrayHasKey(ContentTypeHelper::CONTENT_TYPE_POST, $list);
        $this->assertContains(8603, $list[ContentTypeHelper::CONTENT_TYPE_POST]);
    }

    public function testGetTranslatableStringsReturnsMenuName(): void
    {
        $widget = $this->makeWidget(['menu_name' => 'Menu']);

        $strings = $widget->getTranslatableStrings();

        $this->assertArrayHasKey('7399cf4', $strings);
        $this->assertEquals('Menu', $strings['7399cf4']['menu_name']);
    }

    public function testGetTranslatableStringsReturnsItemTitles(): void
    {
        $widget = $this->makeWidget([
            'menu_items' => [
                ['_id' => 'c819dfc', 'item_title' => 'Products', 'item_dropdown_content' => 'yes'],
                ['_id' => '88cdd5a', 'item_title' => 'Solutions', 'item_dropdown_content' => 'yes'],
                ['_id' => '8934ff0', 'item_title' => 'Support'],
            ],
        ]);

        $strings = $widget->getTranslatableStrings();

        $this->assertEquals('Products', $strings['7399cf4']['menu_items/c819dfc']['item_title']);
        $this->assertEquals('Solutions', $strings['7399cf4']['menu_items/88cdd5a']['item_title']);
        $this->assertEquals('Support', $strings['7399cf4']['menu_items/8934ff0']['item_title']);
    }

    public function testSetTargetContentAppliesTranslatedMenuName(): void
    {
        $proxy = $this->createMock(WordpressFunctionProxyHelper::class);
        $externalContentElementor = $this->createMock(ExternalContentElementor::class);
        $externalContentElementor->method('getWpProxy')->willReturn($proxy);

        $widget = $this->makeWidget(['menu_name' => 'Menu']);

        $strings = [
            '7399cf4' => ['menu_name' => 'Menü'],
        ];

        $result = $widget->setTargetContent(
            $externalContentElementor,
            new RelatedContentInfo([]),
            $strings,
            $this->createMock(SubmissionEntity::class),
        )->toArray();

        $this->assertEquals('Menü', $result['settings']['menu_name']);
    }

    public function testSetTargetContentAppliesTranslatedItemTitles(): void
    {
        $proxy = $this->createMock(WordpressFunctionProxyHelper::class);
        $externalContentElementor = $this->createMock(ExternalContentElementor::class);
        $externalContentElementor->method('getWpProxy')->willReturn($proxy);

        $widget = $this->makeWidget([
            'menu_items' => [
                ['_id' => 'c819dfc', 'item_title' => 'Products'],
                ['_id' => '88cdd5a', 'item_title' => 'Solutions'],
            ],
        ]);

        $strings = [
            '7399cf4' => [
                'menu_items' => [
                    'c819dfc' => ['item_title' => 'Produkte'],
                    '88cdd5a' => ['item_title' => 'Lösungen'],
                ],
            ],
        ];

        $result = $widget->setTargetContent(
            $externalContentElementor,
            new RelatedContentInfo([]),
            $strings,
            $this->createMock(SubmissionEntity::class),
        )->toArray();

        $this->assertEquals('Produkte', $result['settings']['menu_items'][0]['item_title']);
        $this->assertEquals('Lösungen', $result['settings']['menu_items'][1]['item_title']);
    }

    public function testSetTargetContentAppliesTranslatedMenuNameAndItemTitlesTogether(): void
    {
        $proxy = $this->createMock(WordpressFunctionProxyHelper::class);
        $externalContentElementor = $this->createMock(ExternalContentElementor::class);
        $externalContentElementor->method('getWpProxy')->willReturn($proxy);

        $widget = $this->makeWidget([
            'menu_name' => 'Menu',
            'menu_items' => [
                ['_id' => 'c819dfc', 'item_title' => 'Products'],
                ['_id' => '88cdd5a', 'item_title' => 'Solutions'],
            ],
        ]);

        $strings = [
            '7399cf4' => [
                'menu_name' => 'Menü',
                'menu_items' => [
                    'c819dfc' => ['item_title' => 'Produkte'],
                    '88cdd5a' => ['item_title' => 'Lösungen'],
                ],
            ],
        ];

        $result = $widget->setTargetContent(
            $externalContentElementor,
            new RelatedContentInfo([]),
            $strings,
            $this->createMock(SubmissionEntity::class),
        )->toArray();

        $this->assertEquals('Menü', $result['settings']['menu_name']);
        $this->assertEquals('Produkte', $result['settings']['menu_items'][0]['item_title']);
        $this->assertEquals('Lösungen', $result['settings']['menu_items'][1]['item_title']);
    }

    public function testSetTargetContentUpdatesIconId(): void
    {
        $sourceId = 21340;
        $targetId = 99999;

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
            'menu_item_icon' => ['value' => ['url' => 'https://example.com/right.svg', 'id' => $sourceId], 'library' => 'svg'],
        ]);

        $info = $widget->getRelated();

        $result = $widget->setTargetContent(
            $externalContentElementor,
            $info,
            [],
            $submission,
        )->toArray();

        $this->assertEquals($targetId, $result['settings']['menu_item_icon']['value']['id']);
    }
}
