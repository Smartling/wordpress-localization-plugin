<?php

namespace Smartling\ContentTypes\Elementor;

use PHPUnit\Framework\TestCase;
use Smartling\ContentTypes\Elementor\Elements\Unknown;

class TestableElementAbstract extends Unknown
{
    public function publicGetIntSettingByKey(string $key, array $settings): ?int
    {
        return $this->getIntSettingByKey($key, $settings);
    }
}

class ElementAbstractTest extends TestCase
{
    /**
     * @dataProvider settingsProvider
     */
    public function testGetIntSettingByKey(string $key, array $settings, ?int $expected): void
    {
        $element = new TestableElementAbstract(['settings' => $settings]);
        $this->assertEquals($expected, $element->publicGetIntSettingByKey($key, $settings));
    }

    public function settingsProvider(): array
    {
        return [
            ['test', ['test' => 1], 1],
            ['test', ['test' => 1.1], null],
            ['test', ['test' => '1'], 1],
            ['test', ['test' => '1.1'], null],
            ['test', ['test' => 'non_integer'], null],
            ['test', [], null],
            ['test/test', ['test' => ['test' => 1]], 1],
            ['test/test', ['test' => ['test' => '1']], 1],
            ['test/test', ['test' => ['test' => 'non_integer']], null],
            ['test/test', ['test' => []], null],
            ['test/test', [], null],
        ];
    }
}
