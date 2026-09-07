<?php

namespace Smartling\Tests\Smartling\Helpers;

use PHPUnit\Framework\TestCase;
use Smartling\Helpers\NonceVerifier;
use Smartling\Helpers\WordpressFunctionProxyHelper;

class NonceVerifierTest extends TestCase
{
    public function testVerifyReturnsTrueForValidNonce(): void
    {
        $wpProxy = $this->createMock(WordpressFunctionProxyHelper::class);
        $wpProxy->method('wp_verify_nonce')->with('valid-nonce', 'my-action')->willReturn(1);

        $this->assertTrue((new NonceVerifier($wpProxy))->verify('valid-nonce', 'my-action'));
    }

    public function testVerifyReturnsFalseWhenWordpressRejectsNonce(): void
    {
        $wpProxy = $this->createMock(WordpressFunctionProxyHelper::class);
        $wpProxy->method('wp_verify_nonce')->willReturn(false);

        $this->assertFalse((new NonceVerifier($wpProxy))->verify('not-a-valid-nonce', 'my-action'));
    }

    /**
     * @dataProvider nonStringNonceProvider
     */
    public function testVerifyRejectsNonStringNonceWithoutCallingWordpress(mixed $nonce): void
    {
        $wpProxy = $this->createMock(WordpressFunctionProxyHelper::class);
        $wpProxy->expects($this->never())->method('wp_verify_nonce');

        $this->assertFalse((new NonceVerifier($wpProxy))->verify($nonce, 'my-action'));
    }

    public static function nonStringNonceProvider(): array
    {
        return [
            'null' => [null],
            'array' => [['a']],
            'empty string' => [''],
        ];
    }
}
