<?php

namespace Smartling\Tests\Smartling\DbAl\WordpressContentEntities;

use PHPUnit\Framework\TestCase;
use Smartling\DbAl\WordpressContentEntities\PostEntityStd;
use Smartling\Tests\Traits\InvokeMethodTrait;

/**
 * Class ArrayHelperTest
 *
 * @package Smartling\Tests\Smartling\Helpers
 * @covers  \Smartling\DbAl\WordpressContentEntities\PostEntityStdWithPostStatus
 */
class PostEntityStdTest extends TestCase
{

    use InvokeMethodTrait;

    /**
     * @covers       \Smartling\DbAl\WordpressContentEntities\PostEntityStd::areMetadataValuesUnique
     * @dataProvider areMetadataValuesUniqueDataProvider
     */
    public function testTestIsMetaMultiValue($inpudData, $expectedResult)
    {
        $obj = new PostEntityStd();

        self::assertEquals(
            $expectedResult,
            $this->invokeMethod($obj, 'areMetadataValuesUnique', [$inpudData])
        );
    }

    /**
     * @return array
     */
    public function areMetadataValuesUniqueDataProvider()
    {
        return [
            'empty data' => [[], false],
            'one element' => [['foo'], false],
            'two same value elements' => [['foo', 'foo'], false],
            'two different elements' => [['foo', 'bar'], true],
        ];
    }

    /**
     * @covers       \Smartling\DbAl\WordpressContentEntities\PostEntityStd::formatMetadata
     * @dataProvider formatMetadataDataProvider
     * @param array $inputData
     * @param array $expectedResult
     */
    public function testFormatMetadataNoException($inputData, $expectedResult)
    {
        $obj = new PostEntityStd();

        self::assertEquals(
            $expectedResult,
            $this->invokeMethod($obj, 'formatMetadata', [$inputData])
        );
    }

    public function formatMetadataDataProvider()
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

    /**
     * @covers  \Smartling\DbAl\WordpressContentEntities\PostEntityStd::formatMetadata
     */
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
