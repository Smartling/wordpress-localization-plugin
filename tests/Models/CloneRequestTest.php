<?php

namespace Smartling\Tests\Models;

use PHPUnit\Framework\TestCase;
use Smartling\Models\UserCloneRequest;
use Smartling\Services\ContentRelationsHandler;

class CloneRequestTest extends TestCase
{
    public function testFromArray()
    {
        $sourceContentType = 'post';
        $sourceId = 13;
        $targetBlogId = 2;
        $x = UserCloneRequest::fromArray([
            'formAction' => ContentRelationsHandler::FORM_ACTION_UPLOAD,
            'source' => ['id' => [$sourceId], 'contentType' => $sourceContentType],
            'relations' => [
                1 => [$targetBlogId => ['post' => [3]]],
                2 => [$targetBlogId => ['attachment' => [5]]],
            ],
            'targetBlogIds' => (string)$targetBlogId,
        ]);
        $this->assertEquals($sourceId, $x->getContentId());
        $this->assertEquals($sourceContentType, $x->getContentType());
        $this->assertEquals([1 => [$targetBlogId => ['post' => [3]]], 2 => [$targetBlogId => ['attachment' => [5]]]], $x->getRelationsOrdered());
    }
}
