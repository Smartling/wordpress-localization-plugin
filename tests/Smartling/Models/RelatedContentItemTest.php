<?php

namespace Smartling\Tests\Smartling\Models;

use PHPUnit\Framework\TestCase;
use Smartling\ContentTypes\ContentTypeHelper;
use Smartling\Models\Content;
use Smartling\Models\RelatedContentItem;

class RelatedContentItemTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $content = new Content(123, ContentTypeHelper::CONTENT_TYPE_POST);
        $containerId = 'element-id-456';
        $path = 'settings/__dynamic__/link';

        $item = new RelatedContentItem($content, $containerId, $path);

        $this->assertSame($content, $item->getContent());
        $this->assertEquals($containerId, $item->getContainerId());
        $this->assertEquals($path, $item->getPath());
    }
}
