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
            $byTerms = (new $class(['id' => 'w1', 'settings' => [
                'template_id' => '7',
                'post_query_post_type' => 'product',
                'post_query_include' => ['terms'],
                'post_query_exclude' => ['terms', 'manual_selection'],
                'post_query_include_term_ids' => ['14', '15'],
                'post_query_exclude_term_ids' => ['16'],
                'post_query_posts_ids' => ['100'],
                'post_query_exclude_ids' => ['101'],
            ]]))->getRelated()->getRelatedContentList();

            $this->assertEquals(['14', '15'], array_map('strval', $byTerms[ContentTypeHelper::CONTENT_TYPE_TAXONOMY]), $class);
            $this->assertEquals(['7'], array_map('strval', $byTerms[ContentTypeHelper::CONTENT_TYPE_POST]), $class);

            $byId = (new $class(['id' => 'w1', 'settings' => [
                'template_id' => '7',
                'post_query_post_type' => 'by_id',
                'post_query_include' => ['terms'],
                'post_query_exclude' => ['terms', 'manual_selection'],
                'post_query_include_term_ids' => ['14'],
                'post_query_posts_ids' => ['100'],
                'post_query_exclude_ids' => ['101'],
            ]]))->getRelated()->getRelatedContentList();

            $this->assertArrayNotHasKey(ContentTypeHelper::CONTENT_TYPE_TAXONOMY, $byId, $class);
            $this->assertEqualsCanonicalizing(['7', '100'], array_map('strval', $byId[ContentTypeHelper::CONTENT_TYPE_POST]), $class);
        }
    }

    public function testPostsWidgetCollectsQueryRelatedContent(): void
    {
        $byTerms = (new Posts(['id' => 'w1', 'settings' => [
            'posts_post_type' => 'post',
            'posts_include' => ['terms'],
            'posts_exclude' => ['terms'],
            'posts_include_term_ids' => ['3'],
            'posts_exclude_term_ids' => ['4'],
            'posts_posts_ids' => ['50'],
        ]]))->getRelated()->getRelatedContentList();

        $this->assertEquals(['3'], array_map('strval', $byTerms[ContentTypeHelper::CONTENT_TYPE_TAXONOMY]));
        $this->assertArrayNotHasKey(ContentTypeHelper::CONTENT_TYPE_POST, $byTerms);

        $byId = (new Posts(['id' => 'w1', 'settings' => [
            'posts_post_type' => 'by_id',
            'posts_include' => ['terms'],
            'posts_include_term_ids' => ['3'],
            'posts_posts_ids' => ['50'],
        ]]))->getRelated()->getRelatedContentList();

        $this->assertArrayNotHasKey(ContentTypeHelper::CONTENT_TYPE_TAXONOMY, $byId);
        $this->assertEquals(['50'], array_map('strval', $byId[ContentTypeHelper::CONTENT_TYPE_POST]));
    }

    public function testInvalidQueryValuesAreIgnored(): void
    {
        $list = (new LoopGrid(['id' => 'w1', 'settings' => [
            'post_query_include' => ['terms'],
            'post_query_exclude' => ['terms'],
            'post_query_include_term_ids' => 'not-an-array',
            'post_query_exclude_term_ids' => ['', 'abc', '0'],
        ]]))->getRelated()->getRelatedContentList();

        $this->assertArrayNotHasKey(ContentTypeHelper::CONTENT_TYPE_TAXONOMY, $list);
    }

    public function testExcludedContentIsRemapOnly(): void
    {
        $info = (new LoopGrid(['id' => 'w1', 'settings' => [
            'template_id' => '7',
            'post_query_post_type' => 'product',
            'post_query_include' => ['terms'],
            'post_query_exclude' => ['terms', 'manual_selection'],
            'post_query_include_term_ids' => ['14'],
            'post_query_exclude_term_ids' => ['16'],
            'post_query_exclude_ids' => ['101'],
        ]]))->getRelated();

        $own = $info->getOwnRelatedContent('w1');
        $this->assertCount(4, $own);
        $remapOnly = [];
        foreach ($own as $path => $content) {
            if ($content->isRemapOnly()) {
                $remapOnly[] = $path;
            }
        }
        $this->assertEqualsCanonicalizing(['settings/post_query_exclude_term_ids/0', 'settings/post_query_exclude_ids/0'], $remapOnly);
    }

    public function testOnlyExcludedContentSubmitsNothing(): void
    {
        $list = (new LoopGrid(['id' => 'w1', 'settings' => [
            'post_query_exclude' => ['terms', 'manual_selection'],
            'post_query_exclude_term_ids' => ['16'],
            'post_query_exclude_ids' => ['101'],
        ]]))->getRelated()->getRelatedContentList();

        $this->assertSame([], $list);
    }

    public function testNonIntegerIdsAreIgnored(): void
    {
        $list = (new LoopGrid(['id' => 'w1', 'settings' => [
            'post_query_post_type' => 'by_id',
            'post_query_posts_ids' => ['1e3', '1.5', ' ', '-4', '12'],
        ]]))->getRelated()->getRelatedContentList();

        $this->assertEquals(['12'], array_map('strval', $list[ContentTypeHelper::CONTENT_TYPE_POST]));
    }

    public function testInvalidTemplateIdIsIgnored(): void
    {
        foreach ([LoopGrid::class, LoopCarousel::class] as $class) {
            foreach ([['template_id' => 'abc'], ['template_id' => '0'], ['template_id' => ['1']], []] as $settings) {
                $this->assertSame([], (new $class(['id' => 'w1', 'settings' => $settings]))->getRelated()->getRelatedContentList(), $class);
            }
        }
    }

    public function testIdsOfInactiveQueryModesAreIgnored(): void
    {
        $settings = [
            'post_query_include_term_ids' => ['14'],
            'post_query_exclude_term_ids' => ['16'],
            'post_query_posts_ids' => ['100'],
            'post_query_exclude_ids' => ['101'],
        ];
        $modes = [
            'missing modes' => [],
            'other post type' => ['post_query_post_type' => 'product', 'post_query_include' => [], 'post_query_exclude' => ['current_post']],
            'empty modes' => ['post_query_post_type' => '', 'post_query_include' => '', 'post_query_exclude' => null],
        ];
        foreach ($modes as $name => $modeSettings) {
            foreach ([LoopGrid::class, LoopCarousel::class] as $class) {
                $this->assertSame([], (new $class(['id' => 'w1', 'settings' => $settings + $modeSettings]))->getRelated()->getRelatedContentList(), "$class: $name");
                $this->assertSame([], (new $class(['id' => 'w1', 'settings' => $settings + $modeSettings]))->getRelated()->getOwnRelatedContent('w1'), "$class: $name");
            }
        }
    }

    public function testEachSettingFollowsItsOwnMode(): void
    {
        $info = (new Posts(['id' => 'w1', 'settings' => [
            'posts_post_type' => 'post',
            'posts_include' => ['authors'],
            'posts_exclude' => ['manual_selection'],
            'posts_include_term_ids' => ['3'],
            'posts_exclude_term_ids' => ['4'],
            'posts_posts_ids' => ['50'],
            'posts_exclude_ids' => ['51'],
        ]]))->getRelated();

        $this->assertEquals(['settings/posts_exclude_ids/0'], array_keys($info->getOwnRelatedContent('w1')));
    }
}
