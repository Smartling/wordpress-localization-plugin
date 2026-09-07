<?php

namespace Smartling\Tests\Smartling\Helpers;

use PHPUnit\Framework\TestCase;
use Smartling\Helpers\AjaxAuthorizationFailure;
use Smartling\Helpers\AjaxSecurityChecker;
use Smartling\Helpers\WordpressFunctionProxyHelper;

class AjaxSecurityCheckerTest extends TestCase
{
    public function testCheckReturnsInvalidNonceWhenRefererCheckFails(): void
    {
        $wpProxy = $this->createMock(WordpressFunctionProxyHelper::class);
        $wpProxy->method('check_ajax_referer')->willReturn(false);
        $wpProxy->expects($this->never())->method('current_user_can');

        $result = (new AjaxSecurityChecker($wpProxy))->check('my-action', 'my_capability', 'testAction');

        $this->assertSame(AjaxAuthorizationFailure::INVALID_NONCE, $result);
    }

    public function testCheckReturnsInsufficientCapabilityWhenCapabilityMissing(): void
    {
        $wpProxy = $this->createMock(WordpressFunctionProxyHelper::class);
        $wpProxy->method('check_ajax_referer')->willReturn(true);
        $wpProxy->method('current_user_can')->with('my_capability')->willReturn(false);

        $result = (new AjaxSecurityChecker($wpProxy))->check('my-action', 'my_capability', 'testAction');

        $this->assertSame(AjaxAuthorizationFailure::INSUFFICIENT_CAPABILITY, $result);
    }

    public function testCheckReturnsNullWhenAuthorized(): void
    {
        $wpProxy = $this->createMock(WordpressFunctionProxyHelper::class);
        $wpProxy->method('check_ajax_referer')->willReturn(true);
        $wpProxy->method('current_user_can')->willReturn(true);

        $this->assertNull((new AjaxSecurityChecker($wpProxy))->check('my-action', 'my_capability', 'testAction'));
    }

    public function testEnforceSendsInvalidNonceErrorAndReturnsFalse(): void
    {
        $wpProxy = $this->createMock(WordpressFunctionProxyHelper::class);
        $wpProxy->method('check_ajax_referer')->willReturn(false);
        $wpProxy->expects($this->once())->method('wp_send_json_error')->with(['message' => 'Invalid nonce'], 403);

        $this->assertFalse((new AjaxSecurityChecker($wpProxy))->enforce('my-action', 'my_capability', 'testAction'));
    }

    public function testEnforceSendsInsufficientPermissionsErrorAndReturnsFalse(): void
    {
        $wpProxy = $this->createMock(WordpressFunctionProxyHelper::class);
        $wpProxy->method('check_ajax_referer')->willReturn(true);
        $wpProxy->method('current_user_can')->willReturn(false);
        $wpProxy->expects($this->once())->method('wp_send_json_error')->with(['message' => 'Insufficient permissions'], 403);

        $this->assertFalse((new AjaxSecurityChecker($wpProxy))->enforce('my-action', 'my_capability', 'testAction'));
    }

    public function testEnforceReturnsTrueWithoutSendingErrorWhenAuthorized(): void
    {
        $wpProxy = $this->createMock(WordpressFunctionProxyHelper::class);
        $wpProxy->method('check_ajax_referer')->willReturn(true);
        $wpProxy->method('current_user_can')->willReturn(true);
        $wpProxy->expects($this->never())->method('wp_send_json_error');

        $this->assertTrue((new AjaxSecurityChecker($wpProxy))->enforce('my-action', 'my_capability', 'testAction'));
    }
}
