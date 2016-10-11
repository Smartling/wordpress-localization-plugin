<?php

namespace Smartling\Tests\Traits;

use Smartling\Settings\SettingsManager;

/**
 * Class SettingsManagerMock
 * @package Smartling\Tests\Traits
 */
trait SettingsManagerMock
{
    /**
     * @return SettingsManager|\PHPUnit_Framework_MockObject_MockObject
     */
    private function getSettingsManagerMock()
    {
        return $this->getMockBuilder('Smartling\Settings\SettingsManager')
            ->setMethods(['getSingleSettingsProfile'])
            ->disableOriginalConstructor()
            ->getMock();
    }
}