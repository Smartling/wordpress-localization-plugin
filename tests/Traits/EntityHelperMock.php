<?php

namespace Smartling\Tests\Traits;

use Psr\Log\LoggerInterface;
use Smartling\Helpers\SiteHelper;

/**
 * Class EntityHelperMock
 * @package Traits
 */
trait EntityHelperMock
{
    /**
     * @param SiteHelper      $siteHelper
     *
     * @return \PHPUnit_Framework_MockObject_MockObject|\Smartling\Helpers\EntityHelper
     */
    private function mockEntityHelper(SiteHelper $siteHelper)
    {
        $entityHelper = $this->getMockBuilder('Smartling\Helpers\EntityHelper')
            ->setMethods(['getSiteHelper'])
            ->getMock();

        $entityHelper->expects(self::any())
            ->method('getSiteHelper')
            ->willReturn($siteHelper);

        return $entityHelper;
    }
}