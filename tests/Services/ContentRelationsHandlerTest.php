<?php

namespace Smartling\Tests\Services;

use PHPUnit\Framework\TestCase;
use Smartling\Helpers\ArrayHelper;
use Smartling\Models\CloneRequest;
use Smartling\Services\ContentRelationsDiscoveryService;
use Smartling\Services\ContentRelationsHandler;

class ContentRelationsHandlerTest extends TestCase
{
    private $request;
    public function testCreateSubmissionsHandlerCloneNoRelations()
    {
        $service = $this->createMock(ContentRelationsDiscoveryService::class);
        $service->expects($this->once())->method('clone')->willReturnCallback(function (CloneRequest $request) {
            $this->request = $request;
        });
        $x = new class($service) extends ContentRelationsHandler {
            public function returnResponse(array $data, $responseCode = 200): void
            {
            }

            public function returnError($key, $message, $responseCode = 400): void
            {
                TestCase::fail('Should not return error, got ' . $message);
            }
        };
        $x->createSubmissionsHandler(['formAction' => ContentRelationsHandler::FORM_ACTION_CLONE, 'source' => ['id' => [13], 'contentType' => 'post'], 'targetBlogIds' => '2,3']);
        $this->assertInstanceOf(CloneRequest::class, $this->request);
        $this->assertEquals(13, $this->request->getContentId());
        $this->assertEquals('post', $this->request->getContentType());
        $this->assertEquals([], $this->request->getRelationsOrdered(), 'Should be empty array if no relations specified');
        $this->assertEquals([2, 3], $this->request->getTargetBlogIds());
    }

    public function testCreateSubmissionsHandlerCloneRelations()
    {
        $service = $this->createMock(ContentRelationsDiscoveryService::class);
        $service->expects($this->once())->method('clone')->willReturnCallback(function (CloneRequest $request) {
            $this->request = $request;
        });
        $targetBlogId = 2;
        $x = new class($service) extends ContentRelationsHandler {
            public function returnResponse(array $data, $responseCode = 200): void
            {
            }

            public function returnError($key, $message, $responseCode = 400): void
            {
                TestCase::fail('Should not return error, got ' . $message);
            }
        };
        $x->createSubmissionsHandler([
            'formAction' => ContentRelationsHandler::FORM_ACTION_CLONE,
            'source' => ['id' => [13], 'contentType' => 'post'],
            'relations' => [
                1 => [$targetBlogId => ['post' => 3]],
                2 => [$targetBlogId => ['attachment' => 5]],
            ],
            'targetBlogIds' => (string)$targetBlogId
        ]);
        $this->assertInstanceOf(CloneRequest::class, $this->request);
        $this->assertEquals([1 => [$targetBlogId => ['post' => 3]], 2 => [$targetBlogId => ['attachment' => 5]]], $this->request->getRelationsOrdered());
        $this->assertEquals([$targetBlogId => ['attachment' => 5]], ArrayHelper::first($this->request->getRelationsOrdered()), 'Should return deepest level first');
    }
}
