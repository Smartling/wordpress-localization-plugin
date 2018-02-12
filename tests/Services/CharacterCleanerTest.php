<?php

namespace Smartling\Tests\Services;

use PHPUnit\Framework\TestCase;
use Smartling\Services\InvalidCharacterCleaner;

/**
 * Class CharacterCleanerTest
 * @package Smartling\Tests\Services
 * @covers  \Smartling\Services\InvalidCharacterCleaner
 */
class CharacterCleanerTest extends TestCase
{
    /**
     * Data Provider for testRun test
     */
    public function dataProvider()
    {
        return [
            [
                ['1. abc' . chr(3) . 'def'],
                ['1. abcdef'],
            ],
            [
                ['2. abc' . chr(7) . 'def'],
                ['2. abcdef'],
            ],
            [
                ['3. abc' . chr(4) . 'def'],
                ['3. abcdef'],
            ],
            [
                ['4. abc' . chr(0x0A) . 'def'],
                ['4. abc' . chr(0x0A) . 'def'],
            ],
        ];
    }

    /**
     * @covers       \Smartling\Services\InvalidCharacterCleaner::processArray()
     * @dataProvider dataProvider
     *
     * @param array $inputData
     * @param array $expectedResult
     */
    public function testFilterSubmissions($inputData, $expectedResult)
    {
        $obj = new InvalidCharacterCleaner();

        $obj->processArray($inputData);

        self::assertEquals($expectedResult, $inputData);
    }
}