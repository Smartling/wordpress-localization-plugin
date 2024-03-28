<?php

namespace Smartling\Tests\Smartling\Helpers;

use Smartling\Helpers\PlaceholderHelper;
use PHPUnit\Framework\TestCase;

class PlaceholderHelperTest extends TestCase {
    /**
     * @dataProvider hasPlaceholdersDataProvider
     */
    public function testHasPlaceholders(string $string, bool $expected)
    {
        $this->assertEquals($expected, (new PlaceholderHelper())->hasPlaceholders($string));
    }

    public function hasPlaceholdersDataProvider(): array
    {
        return [
            [
                '',
                false,
            ],
            [
                PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_START . '#Content' . PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_END,
                true,
            ],
            [
                PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_START . 'test1' . PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_END
                . ' test2 ' . PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_START . 'test3' . PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_END,
                true,
            ]
        ];
    }


    /**
     * @dataProvider removePlaceholdersDataProvider
     */
    public function testRemovePlaceholders(string $string, string $expected)
    {
        $this->assertEquals($expected, (new PlaceholderHelper())->removePlaceholders($string));
    }

    public function removePlaceholdersDataProvider(): array
    {
        return [
            [
                '',
                '',
            ],
            [
                PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_START . '#Content' . PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_END,
                '#Content',
            ],
            [
                PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_START . '#post_title #separator_sa #site_title' . PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_END .
                ' Post title edited',
                '#post_title #separator_sa #site_title Post title edited',
            ],
            [
                PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_START . '#post_excerpt' . PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_END .
                ' Translation content in the middle ' .
                PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_START . '#separator_sa' . PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_END,
                '#post_excerpt Translation content in the middle #separator_sa',
            ],
        ];
    }
}
