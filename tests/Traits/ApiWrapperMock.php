<?php

namespace Smartling\Tests\Traits;

use PHPUnit\Framework\MockObject\MockObject;
use Smartling\ApiWrapper;
use Smartling\ApiWrapperInterface;

trait ApiWrapperMock
{
    /**
     * @return ApiWrapperInterface|MockObject
     */
    private function getApiWrapperMock()
    {
        return $this->createPartialMock(ApiWrapper::class, [
            'acquireLock',
            'releaseLock',
            'renewLock',
            'downloadFile',
            'getStatus',
            'testConnection',
            'uploadContent',
            'getSupportedLocales',
            'lastModified',
            'getStatusForAllLocales',
        ]);
    }
}