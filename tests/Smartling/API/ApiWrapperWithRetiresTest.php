<?php

namespace Smartling\Tests\Smartling\API;

use PHPUnit\Framework\TestCase;
use Smartling\ApiWrapper;
use Smartling\ApiWrapperWithRetries;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Vendor\Smartling\Exceptions\SmartlingApiException;

class ApiWrapperWithRetiresTest extends TestCase
{
    public function testRetriesAndOriginalExceptionThrown(): void
    {
        $base = $this->createMock(ApiWrapper::class);
        $base->expects($this->exactly(ApiWrapperWithRetries::RETRY_ATTEMPTS))->method('acquireLock')->willThrowException(new SmartlingApiException('test'));
        $x = new ApiWrapperWithRetries($base, 0);
        try {
            $x->acquireLock($this->createMock(ConfigurationProfileEntity::class), 'key', 100500);
        } catch (SmartlingApiException $e) {
            $this->assertEquals('test', $e->getMessage());
        }
    }

    public function testNoRetriesOnUnrecoverableError(): void
    {
        $base = $this->createPartialMock(ApiWrapper::class, ['acquireLock']);
        $base->expects($this->once())->method('acquireLock')->willThrowException(new SmartlingApiException([['key' => 'forbidden']]));
        $x = new ApiWrapperWithRetries($base, 0);
        try {
            $x->acquireLock($this->createMock(ConfigurationProfileEntity::class), 'key', 100500);
        } catch (SmartlingApiException $e) {
            $this->assertEquals('forbidden', $e->getErrors()[0]['key']);
        }
    }
}
