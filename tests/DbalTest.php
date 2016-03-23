<?php

namespace Smartling\Tests;

use Smartling\DbAl\DB;
use Smartling\Tests\Traits\InvokeMethodTrait;

/**
 * Class DbAlTest
 * Test class for \Smartling\DbAl\DB.
 * @package Smartling\Tests
 */
class DbAlTest extends \PHPUnit_Framework_TestCase
{
    use InvokeMethodTrait;

    /**
     * @var  DB|\PHPUnit_Framework_MockObject_MockObject
     */
    private $dbal;


    /**
     * Sets up the fixture, for example, open a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        $mock = $this
            ->getMockBuilder('Smartling\DbAl\DB')
            ->setMethods(['getWpdb'])
            ->disableOriginalConstructor()
            ->getMock();


        $this->dbal = $mock;
    }

    /**
     * @covers       Smartling\DbAl\DB::getCharsetCollate
     * @dataProvider getCharsetCollateDataProvider
     *
     * @param string $charset
     * @param string $collate
     * @param string $expectedResult
     */
    public function testGetCharsetCollate($charset, $collate, $expectedResult)
    {
        $this->dbal
            ->expects(self::any())
            ->method('getWpdb')
            ->willReturn(
                (object)
                [
                    'charset' => $charset,
                    'collate' => $collate,
                ]
            );

        $result = $this->invokeMethod($this->dbal, 'getCharsetCollate', []);

        self::assertEquals($expectedResult, $result);
    }

    /**
     * Data provider for testPreparePermalink method.
     * @return array
     */
    public function getCharsetCollateDataProvider()
    {
        return [
            ['utf8', 'utf8_general_ci', ' DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci '],
            ['', 'utf8_general_ci', ' COLLATE utf8_general_ci '],
            ['utf8', '', ' DEFAULT CHARACTER SET utf8 '],
            ['', '', ''],
        ];
    }
}