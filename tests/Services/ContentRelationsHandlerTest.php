<?php

namespace Smartling\Tests\Services;

use PHPUnit\Framework\TestCase;
use Smartling\Exception\SmartlingDbException;
use Smartling\Exception\SmartlingHumanReadableException;
use Smartling\Helpers\AjaxSecurityChecker;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Models\UserTranslationRequest;
use Smartling\Services\ContentRelationsDiscoveryService;
use Smartling\Services\ContentRelationsHandler;

class ContentRelationsHandlerTest extends TestCase
{
    private $request;
    private function makeWpProxy(bool $currentUserCan = true): WordpressFunctionProxyHelper
    {
        $proxy = $this->createMock(WordpressFunctionProxyHelper::class);
        $proxy->method('check_ajax_referer')->willReturn(1);
        $proxy->method('current_user_can')->willReturn($currentUserCan);
        return $proxy;
    }

    public function testCreateSubmissionsHandlerUploadNoRelations()
    {
        $service = $this->createMock(ContentRelationsDiscoveryService::class);
        $service->expects($this->once())->method('createSubmissions')->willReturnCallback(function (UserTranslationRequest $request) {
            $this->request = $request;
        });
        $proxy = $this->makeWpProxy();
        $x = new class($service, $proxy, new AjaxSecurityChecker($proxy)) extends ContentRelationsHandler {
            public function returnResponse(array $data, $responseCode = 200): void
            {
            }

            public function returnError($key, $message, $responseCode = 400): void
            {
                TestCase::fail('Should not return error, got ' . $message);
            }
        };
        $x->createSubmissionsHandler($this->buildData(['source' => ['id' => [13], 'contentType' => 'post'], 'targetBlogIds' => '2,3']));
        $this->assertInstanceOf(UserTranslationRequest::class, $this->request);
        $this->assertEquals(13, $this->request->getContentId());
        $this->assertEquals('post', $this->request->getContentType());
        $this->assertEquals([], $this->request->getRelationsOrdered(), 'Should be empty array if no relations specified');
        $this->assertEquals([2, 3], $this->request->getTargetBlogIds());
    }

    public function testCreateSubmissionsHandlerUploadRelations()
    {
        $service = $this->createMock(ContentRelationsDiscoveryService::class);
        $service->expects($this->once())->method('createSubmissions')->willReturnCallback(function (UserTranslationRequest $request) {
            $this->request = $request;
        });
        $targetBlogId = 2;
        $proxy = $this->makeWpProxy();
        $x = new class($service, $proxy, new AjaxSecurityChecker($proxy)) extends ContentRelationsHandler {
            public function returnResponse(array $data, $responseCode = 200): void
            {
            }

            public function returnError($key, $message, $responseCode = 400): void
            {
                TestCase::fail('Should not return error, got ' . $message);
            }
        };
        $x->createSubmissionsHandler($this->buildData([
            'source' => ['id' => [13], 'contentType' => 'post'],
            'relations' => [
                1 => [$targetBlogId => ['post' => 3]],
                2 => [$targetBlogId => ['attachment' => 5]],
            ],
            'targetBlogIds' => (string)$targetBlogId,
        ]));
        $this->assertInstanceOf(UserTranslationRequest::class, $this->request);
        $this->assertEquals([1 => [$targetBlogId => ['post' => 3]], 2 => [$targetBlogId => ['attachment' => 5]]], $this->request->getRelationsOrdered());
        $this->assertEquals([$targetBlogId => ['attachment' => 5]], ArrayHelper::first($this->request->getRelationsOrdered()), 'Should return deepest level first');
    }

    public function testCreateSubmissionsHandlerReturns403WhenCapabilityMissing(): void
    {
        $service = $this->createMock(ContentRelationsDiscoveryService::class);
        $service->expects($this->never())->method('createSubmissions');

        $proxy = $this->makeWpProxy(false);

        $x = new class($service, $proxy, new AjaxSecurityChecker($proxy)) extends ContentRelationsHandler {
            public ?string $capturedErrorKey = null;
            public ?int $capturedErrorCode = null;

            public function returnResponse(array $data, $responseCode = 200): void {}

            public function returnError($key, $message, $responseCode = 400): void
            {
                $this->capturedErrorKey = $key;
                $this->capturedErrorCode = $responseCode;
            }
        };

        $x->createSubmissionsHandler($this->buildData(['source' => ['id' => [1], 'contentType' => 'post'], 'targetBlogIds' => '2']));

        $this->assertSame('permission.denied', $x->capturedErrorKey);
        $this->assertSame(403, $x->capturedErrorCode);
    }

    /**
     * A SmartlingHumanReadableException thrown while resolving the profile or validating target blogs must surface
     * its own key/message/response code to the client, not be swallowed into a generic failure.
     */
    public function testCreateSubmissionsHandlerMapsHumanReadableExceptionToItsOwnKeyAndCode(): void
    {
        $service = $this->createMock(ContentRelationsDiscoveryService::class);
        $service->method('createSubmissions')->willThrowException(
            new SmartlingHumanReadableException('Invalid target locale for selected profile', 'target.blog.invalid', 400)
        );
        $proxy = $this->makeWpProxy();

        $x = new class($service, $proxy, new AjaxSecurityChecker($proxy)) extends ContentRelationsHandler {
            public ?string $capturedErrorKey = null;
            public ?string $capturedErrorMessage = null;
            public ?int $capturedErrorCode = null;

            public function returnResponse(array $data, $responseCode = 200): void
            {
                TestCase::fail('Should not return a success response');
            }

            public function returnError($key, $message, $responseCode = 400): void
            {
                $this->capturedErrorKey = $key;
                $this->capturedErrorMessage = $message;
                $this->capturedErrorCode = $responseCode;
            }
        };

        $x->createSubmissionsHandler($this->buildData(['source' => ['id' => [1], 'contentType' => 'post'], 'targetBlogIds' => '2']));

        $this->assertSame('target.blog.invalid', $x->capturedErrorKey);
        $this->assertSame('Invalid target locale for selected profile', $x->capturedErrorMessage);
        $this->assertSame(400, $x->capturedErrorCode);
    }

    /**
     * A SmartlingDbException from unrelated code (DB layer, queue, content handlers, etc.) reachable from
     * createSubmissions() must not be mislabeled as a profile error - its real message must still reach the client.
     */
    public function testCreateSubmissionsHandlerPreservesMessageForUnrelatedDbException(): void
    {
        $service = $this->createMock(ContentRelationsDiscoveryService::class);
        $service->method('createSubmissions')->willThrowException(new SmartlingDbException('Queue table is locked'));
        $proxy = $this->makeWpProxy();

        $x = new class($service, $proxy, new AjaxSecurityChecker($proxy)) extends ContentRelationsHandler {
            public ?string $capturedErrorKey = null;
            public ?string $capturedErrorMessage = null;

            public function returnResponse(array $data, $responseCode = 200): void
            {
                TestCase::fail('Should not return a success response');
            }

            public function returnError($key, $message, $responseCode = 400): void
            {
                $this->capturedErrorKey = $key;
                $this->capturedErrorMessage = $message;
            }
        };

        $x->createSubmissionsHandler($this->buildData(['source' => ['id' => [1], 'contentType' => 'post'], 'targetBlogIds' => '2']));

        $this->assertSame('content.submission.failed', $x->capturedErrorKey);
        $this->assertSame('Queue table is locked', $x->capturedErrorMessage);
    }

    private function buildData(array $overrides = []): array
    {
        return array_merge([
            'formAction' => ContentRelationsHandler::FORM_ACTION_UPLOAD,
            'job' => [
                'id' => '',
                'name' => '',
                'description' => '',
                'dueDate' => '',
                'timeZone' => 'Europe/Kyiv',
                'authorize' => 'true',
            ],
            'profileId' => 5,
        ], $overrides);
    }
}
