<?php

namespace Smartling\Tests\Traits;

use Smartling\ApiWrapperInterface;

/**
 * Class ApiWrapperMock
 * @package Smartling\Tests\Traits
 */
trait ApiWrapperMock
{
    /**
     * @return ApiWrapperInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private function getApiWrapperMock()
    {
        return $this->getMockBuilder('Smartling\ApiWrapper')
            ->setMethods(
                [
                    'downloadFile',
                    'getStatus',
                    'testConnection',
                    'uploadContent',
                    'getSupportedLocales',
                    'lastModified',
                    'getStatusForAllLocales',
                ]
            )
            ->disableOriginalConstructor()
            ->getMock();
    }
}