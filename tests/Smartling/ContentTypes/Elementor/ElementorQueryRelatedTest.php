<?php

namespace Smartling\ContentTypes\Elementor;

use PHPUnit\Framework\TestCase;
use Smartling\ContentTypes\ContentTypeHelper;
use Smartling\ContentTypes\Elementor\Elements\LoopCarousel;
use Smartling\ContentTypes\Elementor\Elements\LoopGrid;
use Smartling\ContentTypes\Elementor\Elements\Posts;

class ElementorQueryRelatedTest extends TestCase
{
    public function testLoopGridType(): void
    {
        $this->assertEquals('loop-grid', (new LoopGrid())->getType());
    }

    public function testLoopWidgetsCollectQueryRelatedContent(): void
    {
        foreach ([LoopGrid::class, LoopCarousel::class] as $class) {
            $list = (new $class(['id' => 'w1', 'settings' => [
                'template_id' => '7',
                'post_query_include_term_ids' => ['14', '15'],
                'post_query_exclude_term_ids' => ['16'],
                'post_query_posts_ids' => ['100'],
                'post_query_exclude_ids' => ['101'],
            ]]))->getRelated()->getRelatedContentList();

            $this->assertEquals(['14', '15', '16'], array_map('strval', $list[ContentTypeHelper::CONTENT_TYPE_TAXONOMY]), $class);
            $this->assertEqualsCanonicalizing(['7', '100', '101'], array_map('strval', $list[ContentTypeHelper::CONTENT_TYPE_POST]), $class);
        }
    }

    public function testPostsWidgetCollectsQueryRelatedContent(): void
    {
        $list = (new Posts(['id' => 'w1', 'settings' => [
            'posts_include_term_ids' => ['3'],
            'posts_exclude_term_ids' => ['4'],
            'posts_posts_ids' => ['50'],
        ]]))->getRelated()->getRelatedContentList();

        $this->assertEquals(['3', '4'], array_map('strval', $list[ContentTypeHelper::CONTENT_TYPE_TAXONOMY]));
        $this->assertEquals(['50'], array_map('strval', $list[ContentTypeHelper::CONTENT_TYPE_POST]));
    }

    public function testInvalidQueryValuesAreIgnored(): void
    {
        $list = (new LoopGrid(['id' => 'w1', 'settings' => [
            'post_query_include_term_ids' => 'not-an-array',
            'post_query_exclude_term_ids' => ['', 'abc', '0'],
        ]]))->getRelated()->getRelatedContentList();

        $this->assertArrayNotHasKey(ContentTypeHelper::CONTENT_TYPE_TAXONOMY, $list);
    }
}
