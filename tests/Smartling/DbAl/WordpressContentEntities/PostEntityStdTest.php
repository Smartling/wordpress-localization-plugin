<?php

namespace Smartling\Tests\Smartling\DbAl\WordpressContentEntities;

use PHPUnit\Framework\TestCase;
use Smartling\DbAl\WordpressContentEntities\PostEntityStd;
use Smartling\Tests\Traits\InvokeMethodTrait;

class PostEntityStdTest extends TestCase
{

    use InvokeMethodTrait;

    /**
     * @dataProvider areMetadataValuesUniqueDataProvider
     */
    public function testTestIsMetaMultiValue($inputData, $expectedResult)
    {
        $obj = new PostEntityStd();

        self::assertEquals(
            $expectedResult,
            $this->invokeMethod($obj, 'areMetadataValuesUnique', [$inputData])
        );
    }

    public function areMetadataValuesUniqueDataProvider(): array
    {
        return [
            'empty data' => [[], false],
            'one element' => [['foo'], false],
            'two same value elements' => [['foo', 'foo'], false],
            'two different elements' => [['foo', 'bar'], true],
        ];
    }

    /**
     * @dataProvider formatMetadataDataProvider
     */
    public function testFormatMetadataNoException(array $inputData, array $expectedResult): void
    {
        $obj = new PostEntityStd();

        self::assertEquals(
            $expectedResult,
            $this->invokeMethod($obj, 'formatMetadata', [$inputData])
        );
    }

    public function formatMetadataDataProvider(): array
    {
        return [
            'one value' => [
                [
                    'foo' => [
                        'bar',
                    ],
                ],
                [
                    'foo' => 'bar',
                ],
            ],
            'two values' => [
                [
                    'foo' => [
                        'bar',
                        'bar',
                    ],
                ],
                [
                    'foo' => 'bar',
                ],
            ],
            'three values' => [
                [
                    'foo' => [
                        'bar',
                        'bar',
                        'bar',
                    ],
                ],
                [
                    'foo' => 'bar',
                ],
            ],
            'complex' => [
                [
                    'foo' => [
                        'bar',
                        'bar',
                    ],
                    'bar' => [
                        'foo',
                        'foo',
                    ],
                ],
                [
                    'foo' => 'bar',
                    'bar' => 'foo',
                ],
            ],
        ];
    }

    public function testFormatMetadataWithUniqueMetavalues()
    {
        $obj = new PostEntityStd();
        $obj->ID = 555;

        $result = $this->invokeMethod(
            $obj,
            'formatMetadata',
            [
                [
                    'foo' => [
                        'foo',
                        'bar',
                    ],
                ],
            ]);

        self::assertEquals(['foo' => 'bar'], $result);
    }
}
