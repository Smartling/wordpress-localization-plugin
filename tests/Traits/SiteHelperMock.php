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
     * @return \PHPUnit_Framework_MockObject_MockObject|\Smartling\Helpers\SiteHelper
     */
    private function mockSiteHelper()
    {
        return $this->getMockBuilder('Smartling\Helpers\SiteHelper')
            ->getMock();
    }

}