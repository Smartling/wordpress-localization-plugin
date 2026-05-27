<?php

namespace Smartling\Tests\Smartling\Tuner;

use PHPUnit\Framework\TestCase;
use Smartling\Tuner\JsonFieldRule;

class JsonFieldRuleTest extends TestCase
{
    public function testConstructAndGetters(): void
    {
        $rule = new JsonFieldRule('page', '_elementor_data', '$.elements[*].settings.title', 'translate');
        $this->assertSame('page', $rule->getContentType());
        $this->assertSame('_elementor_data', $rule->getMetaKey());
        $this->assertSame('$.elements[*].settings.title', $rule->getPropertyPath());
        $this->assertSame('translate', $rule->getReplacerId());
    }

    public function testToArrayAndFromArray(): void
    {
        $rule = new JsonFieldRule('page', '_elementor_data', '$.x', 'related|attachment');
        $arr = $rule->toArray();
        $this->assertSame([
            'contentType' => 'page',
            'metaKey' => '_elementor_data',
            'propertyPath' => '$.x',
            'replacerId' => 'related|attachment',
        ], $arr);
        $this->assertEquals($rule, JsonFieldRule::fromArray($arr));
    }

    public function testFromArrayMissingKeyThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        JsonFieldRule::fromArray(['contentType' => 'page']);
    }
}
