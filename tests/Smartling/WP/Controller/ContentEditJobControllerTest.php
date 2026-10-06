<?php

namespace Smartling\Tests\Smartling\WP\Controller;

use PHPUnit\Framework\TestCase;
use Smartling\ApiWrapperInterface;
use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\Exception\SmartlingDbException;
use Smartling\Helpers\Cache;
use Smartling\Helpers\PluginInfo;
use Smartling\Helpers\SiteHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Settings\Locale;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionManager;
use Smartling\Tests\Traits\InvokeMethodTrait;
use Smartling\WP\Controller\ContentEditJobController;

class ContentEditJobControllerTest extends TestCase
{
    use InvokeMethodTrait;

    private function getController(SettingsManager $settingsManager): ContentEditJobController
    {
        return new ContentEditJobController(
            $this->createMock(ApiWrapperInterface::class),
            $this->createMock(LocalizationPluginProxyInterface::class),
            $this->createMock(PluginInfo::class),
            $settingsManager,
            $this->createMock(SiteHelper::class),
            $this->getMockBuilder(SubmissionManager::class)->disableOriginalConstructor()->getMock(),
            $this->createMock(Cache::class),
            $this->createMock(WordpressFunctionProxyHelper::class),
        );
    }

    private function profileWithId(int $id): ConfigurationProfileEntity
    {
        $profile = new ConfigurationProfileEntity();
        $profile->setId($id);

        return $profile;
    }

    public function testResolveRequestedProfileUsesRequestedWhenValidAndActive()
    {
        $blogId = 1;
        $requested = $this->profileWithId(5);
        $sourceLocale = new Locale();
        $sourceLocale->setBlogId($blogId);
        $requested->setSourceLocale($sourceLocale);
        $requested->setIsActive(1);

        $settingsManager = $this->createMock(SettingsManager::class);
        $settingsManager->expects(self::once())->method('getEntityById')->with(5)->willReturn([$requested]);
        $settingsManager->expects(self::never())->method('getSingleSettingsProfile');

        $result = $this->invokeMethod($this->getController($settingsManager), 'resolveRequestedProfile', [5, $blogId]);

        self::assertSame($requested, $result);
    }

    public function testResolveRequestedProfileFallsBackWhenNoneRequested()
    {
        $blogId = 1;
        $active = $this->profileWithId(3);

        $settingsManager = $this->createMock(SettingsManager::class);
        $settingsManager->expects(self::never())->method('getEntityById');
        $settingsManager->expects(self::once())->method('getSingleSettingsProfile')->with($blogId)->willReturn($active);

        $result = $this->invokeMethod($this->getController($settingsManager), 'resolveRequestedProfile', [null, $blogId]);

        self::assertSame($active, $result);
    }

    public function testResolveRequestedProfileFallsBackWhenRequestedNotFound()
    {
        $blogId = 1;
        $active = $this->profileWithId(3);

        $settingsManager = $this->createMock(SettingsManager::class);
        $settingsManager->method('getEntityById')->with(5)->willReturn([]);
        $settingsManager->expects(self::once())->method('getSingleSettingsProfile')->with($blogId)->willReturn($active);

        $result = $this->invokeMethod($this->getController($settingsManager), 'resolveRequestedProfile', [5, $blogId]);

        self::assertSame($active, $result);
    }

    public function testResolveRequestedProfileFallsBackWhenRequestedBelongsToDifferentBlog()
    {
        $blogId = 1;
        $requested = $this->profileWithId(5);
        $foreignLocale = new Locale();
        $foreignLocale->setBlogId(99);
        $requested->setSourceLocale($foreignLocale);
        $requested->setIsActive(1);
        $active = $this->profileWithId(3);

        $settingsManager = $this->createMock(SettingsManager::class);
        $settingsManager->method('getEntityById')->with(5)->willReturn([$requested]);
        $settingsManager->expects(self::once())->method('getSingleSettingsProfile')->with($blogId)->willReturn($active);

        $result = $this->invokeMethod($this->getController($settingsManager), 'resolveRequestedProfile', [5, $blogId]);

        self::assertSame($active, $result);
    }

    public function testResolveRequestedProfileFallsBackWhenRequestedIsInactive()
    {
        $blogId = 1;
        $requested = $this->profileWithId(5);
        $sourceLocale = new Locale();
        $sourceLocale->setBlogId($blogId);
        $requested->setSourceLocale($sourceLocale);
        $requested->setIsActive(0);
        $active = $this->profileWithId(3);

        $settingsManager = $this->createMock(SettingsManager::class);
        $settingsManager->method('getEntityById')->with(5)->willReturn([$requested]);
        $settingsManager->expects(self::once())->method('getSingleSettingsProfile')->with($blogId)->willReturn($active);

        $result = $this->invokeMethod($this->getController($settingsManager), 'resolveRequestedProfile', [5, $blogId]);

        self::assertSame($active, $result);
    }

    public function testResolveRequestedProfilePropagatesExceptionWhenNoActiveProfileEither()
    {
        $this->expectException(SmartlingDbException::class);
        $blogId = 1;

        $settingsManager = $this->createMock(SettingsManager::class);
        $settingsManager->method('getEntityById')->with(5)->willReturn([]);
        $settingsManager->method('getSingleSettingsProfile')->with($blogId)->willThrowException(new SmartlingDbException('no active profile'));

        $this->invokeMethod($this->getController($settingsManager), 'resolveRequestedProfile', [5, $blogId]);
    }
}
