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

    public function testReplaceDynamicFeaturedImage(): void
    {
        $original = '[elementor-tag id="" name="post-featured-image" settings="%7B%22fallback%22%3A%7B%22url%22%3A%22http%3A%2F%2Ftest.com%2Fwp-content%2Fuploads%2F2025%2F10%2FImage.webp%22%2C%22id%22%3A123%2C%22size%22%3A%22%22%2C%22alt%22%3A%22%22%2C%22source%22%3A%22library%22%7D%7D"]';
        $result = (new Unknown())->replaceDynamicTagSetting($original, '456');
        $this->assertEquals(str_replace('123', '456', $original), $result);
    }
}
