<?php

namespace Smartling\Tests\Traits;

use Psr\Log\LoggerInterface;

/**
 * Class SiteHelperMock
 * @package Traits
 */
trait SiteHelperMock
{
    /**
     * @param LoggerInterface $logger
     *
     * @return \PHPUnit_Framework_MockObject_MockObject|\Smartling\Helpers\SiteHelper
     */
    private function mockSiteHelper(LoggerInterface $logger)
    {
        return $this->getMockBuilder('Smartling\Helpers\SiteHelper')
            ->setConstructorArgs([$logger])
            ->getMock();
    }

}