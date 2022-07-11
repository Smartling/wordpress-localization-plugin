<?php

namespace Smartling\Tests\Smartling\Helpers;

use Smartling\Helpers\PlaceholderHelper;
use PHPUnit\Framework\TestCase;

class PlaceholderHelperTest extends TestCase {
    public function testRemovePlaceholders()
    {
        $x = new PlaceholderHelper();
        $this->assertEquals('', $x->removePlaceholders(null ?? ''));
        $this->assertEquals('', $x->removePlaceholders(''));
        $this->assertEquals('#Content', $x->removePlaceholders(PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_START . '#Content' . PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_END));
        $this->assertEquals('#post_title #separator_sa #site_title Post title edited', $x->removePlaceholders(
            PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_START . '#post_title #separator_sa #site_title' . PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_END .
            ' Post title edited'
        ));
        $this->assertEquals('#post_excerpt Translation content in the middle #separator_sa', $x->removePlaceholders(
            PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_START . '#post_excerpt' . PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_END .
            ' Translation content in the middle ' .
            PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_START . '#separator_sa' . PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_END
        ));
    }
}
