<?php

namespace Smartling\WP\Controller {
    // Bare, unproxied WP globals called by ConfigurationProfileFormController::save().
    // Not stubbed anywhere else for the unit test suite.
    if (!function_exists(__NAMESPACE__ . '\\wp_redirect')) {
        function wp_redirect($location)
        {
            // no-op for tests
        }
    }

    if (!function_exists(__NAMESPACE__ . '\\get_admin_url')) {
        function get_admin_url($blogId = null, $path = '')
        {
            return $path;
        }
    }
}

namespace Smartling\Tests\WP\Controller {

    use PHPUnit\Framework\TestCase;
    use Smartling\ApiWrapperInterface;
    use Smartling\DbAl\LocalizationPluginProxyInterface;
    use Smartling\Helpers\Cache;
    use Smartling\Helpers\PluginInfo;
    use Smartling\Helpers\SiteHelper;
    use Smartling\Settings\ConfigurationProfileEntity;
    use Smartling\Settings\SettingsManager;
    use Smartling\Submissions\SubmissionManager;
    use Smartling\WP\Controller\ConfigurationProfileFormController;

    class ConfigurationProfileFormControllerTest extends TestCase
    {
        private array $storedRequest;

        protected function setUp(): void
        {
            parent::setUp();
            $this->storedRequest = $_REQUEST;
        }

        protected function tearDown(): void
        {
            parent::tearDown();
            $_REQUEST = $this->storedRequest;
        }

        private function createController(SettingsManager $settingsManager, SiteHelper $siteHelper): ConfigurationProfileFormController
        {
            return new ConfigurationProfileFormController(
                $this->createMock(ApiWrapperInterface::class),
                $this->createMock(LocalizationPluginProxyInterface::class),
                $this->createMock(PluginInfo::class),
                $settingsManager,
                $siteHelper,
                $this->createMock(SubmissionManager::class),
                $this->createMock(Cache::class),
            );
        }

        public function testSaveRemovesTargetLocaleMatchingNewSourceLocale(): void
        {
            $profile = new ConfigurationProfileEntity();
            $profile->setId(1);

            $siteHelper = $this->createMock(SiteHelper::class);
            $siteHelper->method('getBlogLabelById')->willReturnCallback(
                static fn(LocalizationPluginProxyInterface $proxy, int $blogId) => 'blog-' . $blogId
            );

            $settingsManager = $this->createMock(SettingsManager::class);
            $settingsManager->method('getEntityById')->with(1)->willReturn([$profile]);
            $settingsManager->expects($this->once())
                ->method('storeEntity')
                ->with($this->callback(function (ConfigurationProfileEntity $savedProfile) {
                    $targetBlogIds = array_map(static fn($locale) => $locale->getBlogId(), $savedProfile->getTargetLocales());
                    // Target locale for blogId 3 (== new source locale) must be dropped,
                    // while the unrelated target locale for blogId 4 must survive.
                    return $savedProfile->getSourceLocale()->getBlogId() === 3
                        && !in_array(3, $targetBlogIds, true)
                        && in_array(4, $targetBlogIds, true)
                        && count($targetBlogIds) === 1;
                }))
                ->willReturnArgument(0);

            $controller = $this->createController($settingsManager, $siteHelper);

            $_REQUEST['smartling_settings'] = [
                'id' => 1,
                'defaultLocale' => '3',
                'targetLocales' => [
                    3 => ['enabled' => 'on', 'target' => 'fr-FR'],
                    4 => ['enabled' => 'on', 'target' => 'de-DE'],
                ],
            ];

            $controller->save();
        }

        public function testSaveKeepsNonCollidingTargetLocalesAndStillDetectsDuplicateSmartlingLocales(): void
        {
            $profile = new ConfigurationProfileEntity();
            $profile->setId(1);

            $siteHelper = $this->createMock(SiteHelper::class);
            $siteHelper->method('getBlogLabelById')->willReturnCallback(
                static fn(LocalizationPluginProxyInterface $proxy, int $blogId) => 'blog-' . $blogId
            );

            $settingsManager = $this->createMock(SettingsManager::class);
            $settingsManager->method('getEntityById')->with(1)->willReturn([$profile]);
            // Duplicate Smartling locale codes among the remaining (non-source) targets
            // must still block the save, same as before this change.
            $settingsManager->expects($this->never())->method('storeEntity');

            $controller = $this->createController($settingsManager, $siteHelper);

            $_REQUEST['smartling_settings'] = [
                'id' => 1,
                'defaultLocale' => '3',
                'targetLocales' => [
                    3 => ['enabled' => 'on', 'target' => 'fr-FR'],
                    4 => ['enabled' => 'on', 'target' => 'de-DE'],
                    5 => ['enabled' => 'on', 'target' => 'de-DE'],
                ],
            ];

            $controller->save();
        }

        public function testRenderLocalesDisablesInputsForSourceLocaleRow(): void
        {
            $controller = $this->createController(
                $this->createMock(SettingsManager::class),
                $this->createMock(SiteHelper::class),
            );

            $method = new \ReflectionMethod(ConfigurationProfileFormController::class, 'renderLocales');

            $html = $method->invoke($controller, ['en-US' => 'English'], 'French', 3, 'fr-FR', true, true);

            $this->assertStringContainsString('disabled="disabled"', $html);
        }

        public function testRenderLocalesDoesNotDisableInputsForRegularRow(): void
        {
            $controller = $this->createController(
                $this->createMock(SettingsManager::class),
                $this->createMock(SiteHelper::class),
            );

            $method = new \ReflectionMethod(ConfigurationProfileFormController::class, 'renderLocales');

            $html = $method->invoke($controller, ['en-US' => 'English'], 'French', 3, 'fr-FR', true, false);

            $this->assertStringNotContainsString('disabled="disabled"', $html);
        }
    }
}
