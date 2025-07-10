<?php

namespace Smartling\ContentTypes\Elementor;

use Smartling\ContentTypes\Elementor\ElementAbstract;
use PHPUnit\Framework\TestCase;
use Smartling\Models\RelatedContentInfo;

class TestableElementAbstract extends ElementAbstract
{
    public function publicGetIntSettingByKey(string $key, array $settings): ?int
    {
        return $this->getIntSettingByKey($key, $settings);
    }

    public function getRelated(): RelatedContentInfo
    {
        return new RelatedContentInfo();
    }

    public function getTranslatableStrings(): array
    {
        return [];
    }

    public function getType(): string
    {
        return 'test';
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
