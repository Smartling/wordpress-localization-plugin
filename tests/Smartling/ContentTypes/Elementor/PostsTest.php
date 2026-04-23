<?php

namespace Smartling\ContentTypes\Elementor;

use PHPUnit\Framework\TestCase;
use Smartling\ContentTypes\ContentTypeHelper;
use Smartling\ContentTypes\Elementor\Elements\Posts;
use Smartling\ContentTypes\ExternalContentElementor;
use Smartling\Models\RelatedContentInfo;
use Smartling\Submissions\SubmissionEntity;

class PostsTest extends TestCase
{
    private function makeWidget(array $settings = []): Posts
    {
        return new Posts([
            'id' => 'abc123',
            'elType' => 'widget',
            'widgetType' => 'posts',
            'settings' => $settings,
            'elements' => [],
        ]);
    }

    public function testGetType(): void
    {
        $this->assertEquals('posts', $this->makeWidget()->getType());
    }

    public function testGetTranslatableStrings(): void
    {
        $strings = $this->makeWidget([
            'classic_read_more_text' => 'Read More »',
            'cards_read_more_text' => 'Read More »',
            'pagination_prev_label' => '« Previous',
            'pagination_next_label' => 'Next »',
            'text' => 'Load More',
            'load_more_no_posts_custom_message' => 'No more posts to show',
            'loadmore_text' => 'Load More',
            'loadmore_loading_text' => 'Loading...',
        ])->getTranslatableStrings();

        $this->assertEquals([
            'classic_read_more_text' => 'Read More »',
            'cards_read_more_text' => 'Read More »',
            'pagination_prev_label' => '« Previous',
            'pagination_next_label' => 'Next »',
            'text' => 'Load More',
            'load_more_no_posts_custom_message' => 'No more posts to show',
            'loadmore_text' => 'Load More',
            'loadmore_loading_text' => 'Loading...',
        ], $strings['abc123']);
    }

    public function testGetTranslatableStringsEmpty(): void
    {
        $this->assertEquals([], $this->makeWidget()->getTranslatableStrings()['abc123']);
    }

    public function testGetRelatedReturnsTemplateId(): void
    {
        $related = $this->makeWidget(['custom_skin_template' => '9165'])->getRelated();

        $this->assertEquals(
            [ContentTypeHelper::CONTENT_TYPE_UNKNOWN => [9165]],
            $related->getRelatedContentList()
        );
    }

    public function testGetRelatedNoTemplateReturnsEmpty(): void
    {
        $related = $this->makeWidget()->getRelated();

        $this->assertEquals([], $related->getRelatedContentList());
    }

    public function testGetRelatedReturnsTermIds(): void
    {
        $related = $this->makeWidget(['posts_include_term_ids' => [42, 99]])->getRelated();

        $this->assertEquals(
            [ContentTypeHelper::CONTENT_TYPE_TAXONOMY => [42, 99]],
            $related->getRelatedContentList()
        );
    }

    public function testGetRelatedSkipsNonNumericTermIds(): void
    {
        $related = $this->makeWidget(['posts_include_term_ids' => ['invalid', 42]])->getRelated();

        $this->assertEquals(
            [ContentTypeHelper::CONTENT_TYPE_TAXONOMY => [42]],
            $related->getRelatedContentList()
        );
    }

    public function testGetRelatedReturnsBothTemplateAndTermIds(): void
    {
        $related = $this->makeWidget([
            'custom_skin_template' => '9165',
            'posts_include_term_ids' => ["42", 99],
        ])->getRelated();

        $this->assertEquals(
            [
                ContentTypeHelper::CONTENT_TYPE_UNKNOWN => [9165],
                ContentTypeHelper::CONTENT_TYPE_TAXONOMY => [42, 99],
            ],
            $related->getRelatedContentList()
        );
    }

    public function testSetTargetContent(): void
    {
        $result = $this->makeWidget([
            'text' => 'Load More',
            'loadmore_loading_text' => 'Loading...',
        ])->setTargetContent(
            $this->createMock(ExternalContentElementor::class),
            new RelatedContentInfo([]),
            ['abc123' => ['text' => 'Cargar más', 'loadmore_loading_text' => 'Cargando...']],
            $this->createMock(SubmissionEntity::class),
        )->toArray();

        $this->assertEquals('Cargar más', $result['settings']['text']);
        $this->assertEquals('Cargando...', $result['settings']['loadmore_loading_text']);
    }
}
