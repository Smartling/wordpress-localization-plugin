<?php

namespace Smartling\Tests\Traits;

use PHPUnit\Framework\MockObject\MockObject;
use Smartling\Settings\SettingsManager;

trait SettingsManagerMock
{
    /**
     * @return SettingsManager|MockObject
     */
    private function getSettingsManagerMock()
    {
        return $this->createPartialMock(SettingsManager::class, [
            'getSingleSettingsProfile',
            'findEntityByMainLocale',
        ]);
    }
}
