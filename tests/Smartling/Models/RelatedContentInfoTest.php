<?php

namespace Smartling\Models;

use PHPUnit\Framework\TestCase;
use Smartling\ContentTypes\ContentTypeHelper;

class RelatedContentInfoTest extends TestCase
{
    public function testIncludeWithNumericalId()
    {
        $targetContentInfo = new RelatedContentInfo();
        $content = new Content(22872, ContentTypeHelper::POST_TYPE_ATTACHMENT);
        $targetContentInfo->addContent($content, 'targetContainerId', 'path');
        $numericalId = '1694689';
        $parentContentInfo = (new RelatedContentInfo())->include($targetContentInfo, $numericalId);
        $rootContentInfo = (new RelatedContentInfo())->include($parentContentInfo, 'rootContainerId');
        $this->assertArrayHasKey($numericalId, $rootContentInfo->getInfo()['rootContainerId']);
        $this->assertEquals(
            ['1694689' => ['targetContainerId' => ['path' => $content]]],
            $rootContentInfo->getInfo()['rootContainerId']
        );
    }

    public function testIncludeWithDifferentKeys()
    {
        $contentInfo1 = new RelatedContentInfo(['containerOne' => ['path1' => new Content(1, ContentTypeHelper::POST_TYPE_ATTACHMENT)]]);
        $contentInfo2 = new RelatedContentInfo(['containerTwo' => ['path2' => new Content(2, ContentTypeHelper::POST_TYPE_ATTACHMENT)]]);
        $mergedContentInfo = $contentInfo1->include($contentInfo2, 'mergedContainer');
        $this->assertArrayHasKey('containerOne', $contentInfo1->getInfo());
        $this->assertArrayHasKey('containerTwo', $mergedContentInfo->getInfo()['mergedContainer']);
    }

    public function testIncludeWithOverlappingKeys()
    {
        $contentInfo1 = new RelatedContentInfo(['sharedContainer' => ['path1' => new Content(1, ContentTypeHelper::POST_TYPE_ATTACHMENT)]]);
        $contentInfo2 = new RelatedContentInfo(['sharedContainer' => ['path2' => new Content(2, ContentTypeHelper::POST_TYPE_ATTACHMENT)]]);
        $mergedContentInfo = $contentInfo1->include($contentInfo2, 'rootContainer');
        $this->assertArrayHasKey('sharedContainer', $mergedContentInfo->getInfo()['rootContainer']);
        $this->assertCount(2, $mergedContentInfo->getInfo()['rootContainer']['sharedContainer']);
    }

    public function testIncludeIsImmutable()
    {
        $originalContentInfo = new RelatedContentInfo(['containerId' => ['path' => new Content(1, ContentTypeHelper::POST_TYPE_ATTACHMENT)]]);
        $newContentInfo = new RelatedContentInfo([]);
        $containerId = 'newContainer';
        $resultContentInfo = $originalContentInfo->include($newContentInfo, $containerId);
        $this->assertArrayNotHasKey($containerId, $originalContentInfo->getInfo());
        $this->assertArrayHasKey($containerId, $resultContentInfo->getInfo());
    }
}
