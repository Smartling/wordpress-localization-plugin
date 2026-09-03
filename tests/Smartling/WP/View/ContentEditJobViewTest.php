<?php

namespace {
    if (!function_exists('plugin_dir_path')) {
        function plugin_dir_path($file)
        {
            return rtrim(dirname($file), '/\\') . '/';
        }
    }

    if (!function_exists('get_current_screen')) {
        function get_current_screen()
        {
            return null;
        }
    }

    if (!function_exists('admin_url')) {
        function admin_url($path = '')
        {
            return 'http://example.com/wp-admin/' . ltrim($path, '/');
        }
    }

    if (!function_exists('wp_create_nonce')) {
        function wp_create_nonce($action = -1)
        {
            return 'test-nonce';
        }
    }
}

namespace Smartling\Tests\Smartling\WP\View {

    use PHPUnit\Framework\TestCase;
    use Smartling\ApiWrapperInterface;
    use Smartling\DbAl\LocalizationPluginProxyInterface;
    use Smartling\Helpers\Cache;
    use Smartling\Helpers\PluginInfo;
    use Smartling\Helpers\SiteHelper;
    use Smartling\Helpers\WordpressFunctionProxyHelper;
    use Smartling\Settings\ConfigurationProfileEntity;
    use Smartling\Settings\SettingsManager;
    use Smartling\Submissions\SubmissionManager;
    use Smartling\Tests\Mocks\WordpressFunctionsMockHelper;
    use Smartling\WP\Controller\ContentEditJobController;

    class ContentEditJobViewTest extends TestCase
    {
        private const VIEW_FILE = __DIR__ . '/../../../../inc/Smartling/WP/View/ContentEditJob.php';

        public static function setUpBeforeClass(): void
        {
            WordpressFunctionsMockHelper::injectFunctionsMocks();
        }

        /**
         * Regression test for a fatal error shipped alongside the clone-request removal:
         * the legacy jQuery view (rendered unconditionally by every post/taxonomy edit
         * screen and the bulk submit page, only visually hidden via CSS) kept referencing
         * ContentRelationsHandler::FORM_ACTION_CLONE after that constant was deleted,
         * which throws "Undefined constant" as soon as the view is actually rendered.
         * Unlike the other tests in this file, this exercises the real PHP template
         * (via WPAbstract::view()) rather than a regex-stripped copy of its markup, so it
         * would have caught that.
         */
        public function testViewRendersWithoutFatalError(): void
        {
            $controller = new ContentEditJobController(
                $this->createMock(ApiWrapperInterface::class),
                $this->createMock(LocalizationPluginProxyInterface::class),
                $this->createMock(PluginInfo::class),
                $this->createMock(SettingsManager::class),
                $this->createMock(SiteHelper::class),
                $this->createMock(SubmissionManager::class),
                $this->createMock(Cache::class),
                $this->createMock(WordpressFunctionProxyHelper::class),
            );

            $profile = $this->createMock(ConfigurationProfileEntity::class);

            ob_start();
            try {
                $controller->view(['profile' => $profile, 'contentType' => 'post']);
            } finally {
                $html = ob_get_clean();
            }

            $this->assertStringContainsString('id="smartling-app"', $html);
            $this->assertStringContainsString('id="createJob"', $html);
            $this->assertStringContainsString("formAction: 'upload'", $html);
        }

        public function testHtmlDivTagsBalanceOnTaxonomyEditScreen(): void
        {
            $html = $this->resolveTemplate(true);
            $this->assertDivBalanced($html, 'taxonomy edit screen ($needWrapper=true)');
        }

        public function testHtmlDivTagsBalanceOnPostEditScreen(): void
        {
            $html = $this->resolveTemplate(false);
            $this->assertDivBalanced($html, 'post edit screen ($needWrapper=false)');
        }

        public function testHtmlDivTagsBalanceOnBulkSubmitScreen(): void
        {
            $html = $this->resolveTemplate(false, true);
            $this->assertDivBalanced($html, 'bulk submit screen ($isBulkSubmitPage=true)');
        }

        public function testHiddenWrapperHasClosingTag(): void
        {
            $html = $this->resolveTemplate(false);

            $this->assertSame(
                1,
                preg_match(
                    '#<div\s+style\s*=\s*"display:\s*none[^"]*"\s*>#i',
                    $html,
                    $matches,
                    PREG_OFFSET_CAPTURE
                ),
                'Hidden display:none wrapper opening <div> should exist'
            );

            $tail = substr($html, $matches[0][1]);
            $opens = preg_match_all('#<div\b#i', $tail);
            $closes = preg_match_all('#</div\s*>#i', $tail);

            $this->assertSame(
                $opens,
                $closes,
                'Hidden display:none wrapper must have a matching closing </div> ' .
                '(an unclosed wrapper here is what caused WP-1004).'
            );
        }

        private function resolveTemplate(bool $needWrapper, bool $isBulkSubmitPage = false): string
        {
            $source = $this->stripNonHtml(file_get_contents(self::VIEW_FILE));

            $source = preg_replace_callback(
                '#<\?php\s+if\s*\(\s*\$needWrapper\s*\)\s*:\s*\?>((?:(?!<\?php\s+endif).)*)<\?php\s+endif\s*;\s*\?>#s',
                static fn(array $m): string => $needWrapper ? $m[1] : '',
                $source
            );

            $source = preg_replace_callback(
                '#<\?php\s.*?if\s*\(\s*!\$isBulkSubmitPage\s*\)\s*:\s*\?>((?:(?!<\?php\s+endif).)*)<\?php\s+endif\s*;\s*\?>#s',
                static fn(array $m): string => $isBulkSubmitPage ? '' : $m[1],
                $source
            );

            $source = preg_replace('#<\?(?:php|=).*?\?>#s', '', $source);

            return $source;
        }

        private function stripNonHtml(string $source): string
        {
            $source = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $source);
            $source = preg_replace('#<style\b[^>]*>.*?</style>#is', '', $source);

            return $source;
        }

        private function assertDivBalanced(string $html, string $context): void
        {
            $opens = preg_match_all('#<div\b#i', $html);
            $closes = preg_match_all('#</div\s*>#i', $html);

            $this->assertSame(
                $opens,
                $closes,
                sprintf(
                    'Unbalanced <div> tags on %s: %d opens vs %d closes. ' .
                    'A mismatch here is what caused WP-1004 (postboxes below the Smartling box refusing to open).',
                    $context,
                    $opens,
                    $closes
                )
            );
        }
    }
}
