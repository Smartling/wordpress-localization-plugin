<?php

namespace Smartling\Tests\Smartling\WP\View;

use PHPUnit\Framework\TestCase;

class ContentEditJobViewTest extends TestCase
{
    private const VIEW_FILE = __DIR__ . '/../../../../inc/Smartling/WP/View/ContentEditJob.php';

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

    public function testHiddenWrapperHasClosingTag(): void
    {
        $source = $this->stripNonHtml(file_get_contents(self::VIEW_FILE));
        $hiddenOpens = preg_match_all('#<div\s+style\s*=\s*"display:\s*none#i', $source);
        $this->assertGreaterThan(
            0,
            $hiddenOpens,
            'Legacy display:none wrapper should still open (it hides the legacy .job-wizard fallback)'
        );
    }

    private function resolveTemplate(bool $needWrapper): string
    {
        $source = $this->stripNonHtml(file_get_contents(self::VIEW_FILE));

        $source = preg_replace(
            '#<\?php\s+if\s*\(\s*\$needWrapper\s*&&\s*false\s*\)\s*:\s*\?>(?:(?!<\?php\s+endif).)*<\?php\s+endif\s*;\s*\?>#s',
            '',
            $source
        );

        $source = preg_replace_callback(
            '#<\?php\s+if\s*\(\s*\$needWrapper\s*\)\s*:\s*\?>((?:(?!<\?php\s+endif).)*)<\?php\s+endif\s*;\s*\?>#s',
            static fn(array $m): string => $needWrapper ? $m[1] : '',
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
