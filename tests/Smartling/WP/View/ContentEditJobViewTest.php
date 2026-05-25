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
