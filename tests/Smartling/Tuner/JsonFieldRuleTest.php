<?php

namespace Smartling\Tests\Smartling\Tuner;

use PHPUnit\Framework\TestCase;
use Smartling\Tuner\JsonFieldRule;

class JsonFieldRuleTest extends TestCase
{
    public function testConstructAndGetters(): void
    {
        $rule = new JsonFieldRule('_elementor_data', '$.elements[*].settings.title', 'translate');
        $this->assertSame('_elementor_data', $rule->getMetaKey());
        $this->assertSame('$.elements[*].settings.title', $rule->getPropertyPath());
        $this->assertSame('translate', $rule->getReplacerId());
    }

    public function testToArrayAndFromArray(): void
    {
        $rule = new JsonFieldRule('_elementor_data', '$.x', 'related|attachment');
        $arr = $rule->toArray();
        $this->assertSame([
            'metaKey' => '_elementor_data',
            'propertyPath' => '$.x',
            'replacerId' => 'related|attachment',
        ], $arr);
        $this->assertEquals($rule, JsonFieldRule::fromArray($arr));
    }

    public function testFromArrayMissingKeyThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        JsonFieldRule::fromArray(['metaKey' => '_elementor_data']);
    }

    public function testFromArrayIgnoresLegacyContentTypeField(): void
    {
        $rule = JsonFieldRule::fromArray([
            'contentType' => 'page',
            'metaKey' => '_elementor_data',
            'propertyPath' => '$.x',
            'replacerId' => 'copy',
        ]);
        $this->assertSame('_elementor_data', $rule->getMetaKey());
        $this->assertSame('$.x', $rule->getPropertyPath());
        $this->assertSame('copy', $rule->getReplacerId());
    }

    public function testExtendedFieldsRoundTrip(): void
    {
        $conditions = [['ancestor' => 2, 'key' => 'pattern_type', 'value' => 'sovos-list-items']];
        $rule = new JsonFieldRule('_elementor_data', '$..a-b.text', 'translate', 'sovos-hero', $conditions);
        $this->assertTrue($rule->isExtended());
        $this->assertSame(['a-b', 'text'], $rule->getKeySuffix());
        $this->assertEquals($rule, JsonFieldRule::fromArray($rule->toArray()));
        $this->assertSame('sovos-hero', $rule->toArray()['widgetType']);
        $this->assertSame($conditions, $rule->toArray()['conditions']);
    }

    public function testLegacyRuleIsNotExtendedAndOmitsNewFields(): void
    {
        $rule = new JsonFieldRule('_elementor_data', '$.elements[*].x', 'copy');
        $this->assertFalse($rule->isExtended());
        $this->assertSame(['metaKey', 'propertyPath', 'replacerId'], array_keys($rule->toArray()));
    }

    /**
     * @dataProvider invalidExtendedProvider
     */
    public function testInvalidExtendedRuleThrows(string $path, string $widgetType, array $conditions): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new JsonFieldRule('_elementor_data', $path, 'translate', $widgetType, $conditions);
    }

    public static function invalidExtendedProvider(): array
    {
        $ok = ['ancestor' => 0, 'key' => 'k', 'value' => 'v'];
        return [
            'positional path with widget' => ['$.elements[*].x', 'w', []],
            'bad widget type' => ['$..x', 'bad widget!', []],
            'too many conditions' => ['$..x', '', array_fill(0, 6, $ok)],
            'condition missing value' => ['$..x', '', [['ancestor' => 0, 'key' => 'k']]],
            'negative ancestor' => ['$..x', '', [['ancestor' => -1, 'key' => 'k', 'value' => 'v']]],
            'empty key' => ['$..x', '', [['ancestor' => 0, 'key' => '', 'value' => 'v']]],
        ];
    }
}
