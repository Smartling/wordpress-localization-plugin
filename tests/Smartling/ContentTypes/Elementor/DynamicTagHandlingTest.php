<?php

namespace Smartling\Tests\Smartling\ContentTypes\Elementor;

use PHPUnit\Framework\TestCase;
use Smartling\ContentTypes\Elementor\Elements\Unknown;

class DynamicTagHandlingTest extends TestCase
{
    public function testReplaceDynamicInternalUrl(): void
    {
        $original = '[elementor-tag id="3039f16" name="internal-url" settings="%7B%22type%22%3A%22post%22%2C%22post_id%22%3A%22123%22%7D"]';
        $result = (new Unknown())->replaceDynamicTagSetting($original, '456');
        $this->assertEquals(str_replace('123', '456', $original), $result);
    }

    public function testReplaceDynamicPopup(): void
    {
        $original = '[elementor-tag id="71944c2" name="popup" settings="%7B%22popup%22%3A%22123%22%7D"]';
        $result = (new Unknown())->replaceDynamicTagSetting($original, '456');
        $this->assertEquals(str_replace('123', '456', $original), $result);
    }
}
