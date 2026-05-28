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
}
