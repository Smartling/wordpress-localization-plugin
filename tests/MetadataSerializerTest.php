<?php

namespace Smartling\Tests;

use PHPUnit\Framework\TestCase;
use Smartling\Helpers\FieldsFilterHelper;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Tests\Traits\DummyLoggerMock;
use Smartling\Tests\Traits\SettingsManagerMock;

/**
 * Class MetadataSerializerTest
 * @package Smartling\Tests
 * @covers  \Smartling\Helpers\FieldsFilterHelper
 */
class MetadataSerializerTest extends TestCase
{

    use DummyLoggerMock;
    use SettingsManagerMock;

    /**
     * Sets up the fixture, for example, open a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
    }


    /**
     * @covers       \Smartling\Helpers\FieldsFilterHelper::prepareSourceData()
     * @dataProvider prepareSourceDataDataProvider
     *
     * @param array $entityFields
     * @param array $expectedResult
     */
    public function testPrepareSourceData(array $entityFields, array $expectedResult)
    {
        $obj = new FieldsFilterHelper($this->getSettingsManagerMock());
        $actualResult = $obj->prepareSourceData($entityFields);
        self::assertEquals($expectedResult, $actualResult);
    }

    public function prepareSourceDataDataProvider()
    {
        return [
            [
                [
                    'entity' => ['a' => 'text 1', 'b' => 'text 2'],
                ],
                [
                    'entity' => ['a' => 'text 1', 'b' => 'text 2'],
                ],
            ],
            [
                [
                    'entity' => ['a' => 'text 1', 'b' => 'text 2'],
                    'meta'   => [],
                ],
                [
                    'entity' => ['a' => 'text 1', 'b' => 'text 2'],
                    'meta'   => [],
                ],
            ],
            [
                [
                    'entity' => ['a' => 'text 1', 'b' => 'text 2'],
                    'meta'   => ['a' => 'b', 'c' => ['d']],
                ],
                [
                    'entity' => ['a' => 'text 1', 'b' => 'text 2'],
                    'meta'   => ['a' => 'b', 'c' => ['d']],
                ],
            ],
            [
                [
                    'entity' => ['a' => 'text 1', 'b' => 'text 2'],
                    'meta'   => ['a' => 'b', 'c' => 'a:2:{s:1:"a";s:1:"b";s:1:"c";s:1:"d";}'],
                ],
                [
                    'entity' => ['a' => 'text 1', 'b' => 'text 2'],
                    'meta'   => ['a' => 'b', 'c' => ['a' => 'b', 'c' => 'd']],
                ],
            ],
            [
                [
                    'entity' => ['a' => 'text 1', 'b' => 'text 2'],
                    'meta'   => ['a' => 'b',
                                 'c' => 'a:1:{s:1:"a";a:3:{s:1:"a";s:4:"val1";s:1:"b";s:4:"val2";s:1:"c";s:4:"val3";}}'],
                ],
                [
                    'entity' => ['a' => 'text 1', 'b' => 'text 2'],
                    'meta'   => ['a' => 'b', 'c' => ['a' => ['a' => 'val1', 'b' => 'val2', 'c' => 'val3']]],
                ],
            ],
        ];
    }

    /**
     * @covers       \Smartling\Helpers\FieldsFilterHelper::applyTranslatedValues()
     * @dataProvider applyTranslatedValuesDataProvider
     *
     * @param array $originalValues
     * @param array $translatedValues
     * @param array $expectedResult
     */
    public function testApplyTranslatedValues(array $originalValues, array $translatedValues, array $expectedResult)
    {
        $submission = SubmissionEntity::fromArray(
            [

                'id'                     => 1,
                'source_title'           => 'nothing',
                'source_blog_id'         => 1,
                'source_content_hash'    => md5(''),
                'content_type'           => 'post',
                'source_id'              => 5,
                'file_uri'               => 'no.xml',
                'target_locale'          => 'en_gb',
                'target_blog_id'         => 7,
                'target_id'              => 14,
                'submitter'              => 'noone',
                'submission_date'        => '2000-01-01',
                'applied_date'           => '2000-01-01',
                'approved_string_count'  => 5,
                'completed_string_count' => 2,
                'word_count'             => 5,
                'status'                 => 'In Progress',
                'is_locked'              => 0,
                'last_modified'          => '2000-01-01',
                'outdated'               => 0,
                'last_error'             => '',


            ], $this->getLogger());
        $obj = new FieldsFilterHelper($this->getSettingsManagerMock());
        $actualResult = $obj->applyTranslatedValues($submission, $originalValues, $translatedValues, false);
        self::assertEquals($expectedResult, $actualResult);
    }

    public function applyTranslatedValuesDataProvider()
    {
        return [
            [
                [
                    'entity' => ['a' => 'text 1', 'b' => 'text 2'],
                ],
                [
                    'entity' => ['a' => 'Das text'],
                ],
                [
                    'entity' => ['a' => 'Das text', 'b' => 'text 2'],
                ],
            ],
            [
                [
                    'entity' => ['a' => 'text 1', 'b' => 'text 2'],
                    'meta'   => [],
                ],
                [
                    'entity' => ['a' => 'Das text'],
                ],
                [
                    'entity' => ['a' => 'Das text', 'b' => 'text 2'],
                ],
            ],
            [
                [
                    'entity' => ['a' => 'text 1', 'b' => 'text 2'],
                    'meta'   => ['a' => 'b', 'c' => ['d']],
                ],
                [
                    'entity' => ['b' => 'Uno'],
                    'meta'   => ['a' => 'Solo'],
                ],
                [
                    'entity' => ['a' => 'text 1', 'b' => 'Uno'],
                    'meta'   => ['a' => 'Solo', 'c' => ['d']],
                ],
            ],
            [
                [
                    'entity' => ['a' => 'text 1', 'b' => 'text 2'],
                    'meta'   => ['a' => 'b', 'c' => 'a:2:{s:1:"a";s:1:"b";s:1:"c";s:1:"d";}'],
                ],
                [
                    'meta' => ['c' => ['c' => 'e']],
                ],
                [
                    'entity' => ['a' => 'text 1', 'b' => 'text 2'],
                    'meta'   => ['a' => 'b', 'c' => ['a' => 'b', 'c' => 'e']],
                ],
            ],
            [
                [
                    'entity' => ['a' => 'text 1', 'b' => 'text 2'],
                    'meta'   => ['a' => 'b',
                                 'c' => 'a:1:{s:1:"a";a:3:{s:1:"a";s:4:"val1";s:1:"b";s:4:"val2";s:1:"c";s:4:"val3";}}'],
                ],
                [
                    'meta' => ['c' => ['a' => ['c' => 'value drei']]],
                ],
                [
                    'entity' => ['a' => 'text 1', 'b' => 'text 2'],
                    'meta'   => ['a' => 'b', 'c' => ['a' => ['a' => 'val1', 'b' => 'val2', 'c' => 'value drei']]],
                ],
            ],
        ];
    }

}