<?php

namespace Smartling\Tests\Smartling\ContentTypes;

use Smartling\ContentTypes\ContentTypeModifyingInterface;
use Smartling\ContentTypes\ContentTypePluggableInterface;
use Smartling\ContentTypes\ExternalContentManager;
use PHPUnit\Framework\TestCase;
use Smartling\Helpers\PluginHelper;
use Smartling\Submissions\SubmissionEntity;

class ExternalContentManagerTest extends TestCase {
    public function testExceptionHandling()
    {
        $content1 = $this->createMock(ContentTypePluggableInterface::class);
        $content1->method('canHandle')->willReturn(true);
        $content1->method('getContentFields')->willThrowException(new \JsonException());
        $content1->method('getRelatedContent')->willThrowException(new \Exception());
        $content1->method('setContentFields')->willThrowException(new \RuntimeException());
        $content2 = $this->createMock(ContentTypeModifyingInterface::class);
        $content2->method('canHandle')->willReturn(true);
        $content2->method('getContentFields')->willThrowException(new \TypeError());
        $content2->method('getRelatedContent')->willThrowException(new \ParseError());
        $content2->method('setContentFields')->willThrowException(new \Error());
        $x = new ExternalContentManager(new PluginHelper());
        $x->addHandler($content1);
        $x->addHandler($content2);
        $submission = $this->createMock(SubmissionEntity::class);
        $this->assertEquals([], $x->getExternalContent([], $submission, false));
        $this->assertEquals([], $x->getExternalRelations('post', 1));
        $this->assertEquals([], $x->setExternalContent([], [], $submission));
    }

    public function testGetExternalContentNotAltered()
    {
        $content1 = $this->createMock(ContentTypePluggableInterface::class);
        $content1->method('canHandle')->willReturn(true);
        $content1->method('getPluginId')->willReturn('content1');
        $content2 = $this->createMock(ContentTypePluggableInterface::class);
        $content2->method('canHandle')->willReturn(false);
        $content2->method('getContentFields')->willReturn(['content2' => ['content_2' => 'content 2']]);
        $content3 = $this->createMock(ContentTypePluggableInterface::class);
        $x = new ExternalContentManager(new PluginHelper());
        $x->addHandler($content1);
        $x->addHandler($content2);
        $x->addHandler($content3);
        $originalContent = [
            'entity' => [
                'post_content' => 'post content',
                'post_title' => 'post title',
            ],
            'meta' => [
                'some_meta' => 'some meta',
            ],
        ];
        $expected = array_merge($originalContent, ['content1' => []]);
        $this->assertEquals($expected, $x->getExternalContent($originalContent, $this->createMock(SubmissionEntity::class), false));
    }

    public function testGetExternalContentExtraFields()
    {
        $content1 = $this->createMock(ContentTypePluggableInterface::class);
        $content1->method('canHandle')->willReturn(true);
        $content1->method('getContentFields')->willReturn(['content_1' => 'content 1']);
        $content1->method('getPluginId')->willReturn('content1');
        $content2 = $this->createMock(ContentTypePluggableInterface::class);
        $content2->method('canHandle')->willReturn(true);
        $content2->method('getContentFields')->willReturn(['content_2' => 'content 2']);
        $content2->method('getPluginId')->willReturn('content2');
        $content3 = $this->createMock(ContentTypePluggableInterface::class);
        $content3->method('canHandle')->willReturn(true);
        $content3->method('getContentFields')->willReturn(['content_3' => 'content 3']);
        $content3->method('getPluginId')->willReturn('content3');
        $x = new ExternalContentManager(new PluginHelper());
        $x->addHandler($content1);
        $x->addHandler($content2);
        $x->addHandler($content3);
        $originalContent = [
            'entity' => [
                'post_content' => 'post content',
                'post_title' => 'post title',
            ],
            'meta' => [
                'some_meta' => 'some meta',
            ]
        ];
        $expected = array_merge($originalContent, [
            'content1' => [
                'content_1' => 'content 1',
            ],
            'content2' => [
                'content_2' => 'content 2',
            ],
            'content3' => [
                'content_3' => 'content 3',
            ],
        ]);
        $this->assertEquals($expected, $x->getExternalContent($originalContent, $this->createMock(SubmissionEntity::class), false));
    }

    public function testGetExternalContentAlteringContent()
    {
        $content1 = $this->createMock(ContentTypeModifyingInterface::class);
        $content1->method('canHandle')->willReturn(true);
        $content1->method('getContentFields')->willReturn(['content_1' => 'content 1']);
        $content1->method('getPluginId')->willReturn('content1');
        $content1->method('alterContentFieldsForUpload')->willReturnArgument(1);
        $content2 = $this->createMock(ContentTypeModifyingInterface::class);
        $content2->method('canHandle')->willReturn(true);
        $content2->method('getContentFields')->willReturn(['content_2' => 'content 2']);
        $content2->method('getPluginId')->willReturn('content2');
        $content2->method('alterContentFieldsForUpload')->willReturnCallback(static function ($value) {
            unset ($value['meta']['removed_meta']);
            return $value;
        });
        $content3 = $this->createMock(ContentTypeModifyingInterface::class);
        $content3->method('canHandle')->willReturn(true);
        $content3->method('getContentFields')->willReturn(['content_3' => 'content 3']);
        $content3->method('getPluginId')->willReturn('content3');
        $content3->method('alterContentFieldsForUpload')->willReturnCallback(static function ($value) {
            unset ($value['entity']['post_content']);
            return $value;
        });
        $originalContent = [
            'entity' => [
                'post_content' => 'post content',
                'post_title' => 'post title',
            ],
            'meta' => [
                'some_meta' => 'some meta',
                'removed_meta' => 'removed meta',
            ]
        ];
        $expected = array_merge([
            'entity' => [
                'post_title' => 'post title',
            ],
            'meta' => [
                'some_meta' => 'some meta',
            ],
        ], [
            'content1' => [
                'content_1' => 'content 1',
            ],
            'content2' => [
                'content_2' => 'content 2',
            ],
            'content3' => [
                'content_3' => 'content 3',
            ],
        ]);
        $x = new ExternalContentManager(new PluginHelper());
        $x->addHandler($content1);
        $x->addHandler($content2);
        $x->addHandler($content3);
        $this->assertEquals($expected, $x->getExternalContent($originalContent, $this->createMock(SubmissionEntity::class), false));
    }

    public function testGetExternalRelations()
    {
        $content1 = $this->createMock(ContentTypePluggableInterface::class);
        $content1->method('canHandle')->willReturn(true);
        $content1->method('getRelatedContent')->willReturn(['post' => 1]);
        $content2 = $this->createMock(ContentTypeModifyingInterface::class);
        $content2->method('canHandle')->willReturn(true);
        $content2->method('getRelatedContent')->willReturn(['image' => 2]);
        $content3 = $this->createMock(ContentTypeModifyingInterface::class);
        $content3->method('canHandle')->willReturn(true);
        $content3->method('getRelatedContent')->willReturn(['post' => 2, 'image' => 1, 'other' => [3]]);
        $expected = [
            'post' => [1, 2],
            'image' => [2, 1],
            'other' => [3],
        ];
        $x = new ExternalContentManager(new PluginHelper());
        $x->addHandler($content1);
        $x->addHandler($content2);
        $x->addHandler($content3);
        $this->assertEquals($expected, $x->getExternalRelations('post', 17));
    }
}
