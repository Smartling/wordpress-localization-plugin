<?php

namespace Smartling\Tests\Smartling\Settings;

use PHPUnit\Framework\TestCase;
use Smartling\Exception\SmartlingDbException;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Settings\TargetLocale;
use Smartling\Tests\Traits\SettingsManagerMock;

/**
 * Class SettingsManagerTest
 * @package Smartling\Tests\Smartling\Settings
 * @covers  \Smartling\Settings\SettingsManager
 */
class SettingsManagerTest extends TestCase
{

    use SettingsManagerMock;

    /**
     * @covers \Smartling\Settings\SettingsManager::getProfileTargetBlogIdsByMainBlogId
     * @expectedException \Smartling\Exception\SmartlingDbException
     */
    public function testGetProfileTargetBlogIdsByMainBlogIdWithDbException()
    {

        $mock = $this->getSettingsManagerMock();

        $mock
            ->expects(self::once())
            ->method('getSingleSettingsProfile')
            ->with(5)
            ->willReturnCallback(
                function () {
                    throw new SmartlingDbException();
                });

        $mock->getProfileTargetBlogIdsByMainBlogId(5);

    }

    /**
     * @covers \Smartling\Settings\SettingsManager::getProfileTargetBlogIdsByMainBlogId
     */
    public function testGetProfileTargetBlogIdsByMainBlogId()
    {

        $mock = $this->getSettingsManagerMock();

        $profile = new ConfigurationProfileEntity();

        $expectedLocales = [2,3,7];

        $profile->setId(5);
        $profile->setTargetLocales([
            TargetLocale::fromArray(['smartlingLocale' => 'en', 'enabled' => 1, 'blogId' => 2]),
            TargetLocale::fromArray(['smartlingLocale' => 'fr', 'enabled' => 1, 'blogId' => 3]),
            TargetLocale::fromArray(['smartlingLocale' => 'cn', 'enabled' => 0, 'blogId' => 4]),
            TargetLocale::fromArray(['smartlingLocale' => 'zh', 'enabled' => 0, 'blogId' => 6]),
            TargetLocale::fromArray(['smartlingLocale' => 'it', 'enabled' => 1, 'blogId' => 7]),
        ]);

        $mock
            ->expects(self::once())
            ->method('getSingleSettingsProfile')
            ->with(5)
            ->willReturn($profile);

        self::assertEquals($expectedLocales, $mock->getProfileTargetBlogIdsByMainBlogId(5));
    }

    /**
     * @covers \Smartling\Settings\SettingsManager::getProfileTargetBlogIdsByMainBlogId
     * @expectedException \Smartling\Exception\SmartlingConfigException
     * @expectedExceptionMessage No active target locales found for profile id=5.
     */
    public function testGetProfileTargetBlogIdsByMainBlogIdWithConfigException()
    {
        $mock = $this->getSettingsManagerMock();
        $profile = new ConfigurationProfileEntity();

        $profile->setId(5);
        $profile->setTargetLocales([]);

        $mock
            ->expects(self::once())
            ->method('getSingleSettingsProfile')
            ->with(5)
            ->willReturn($profile);

        $mock->getProfileTargetBlogIdsByMainBlogId(5);
    }
}
