<?php

namespace Smartling\Tests;

use PHPUnit\Framework\TestCase;
use Smartling\DbAl\DB;
use Smartling\Tests\Traits\InvokeMethodTrait;

class DbAlTest extends TestCase
{
    use InvokeMethodTrait;

    private $dbal;

    protected function setUp(): void
    {
        $this->dbal = $this->createPartialMock( DB::class, ['getWpdb']);
    }

    /**
     * @dataProvider getCharsetCollateDataProvider
     *
     * @param string $charset
     * @param string $collate
     * @param string $expectedResult
     */
    public function testGetCharsetCollate(string $charset, string $collate, string $expectedResult)
    {
        $this->dbal
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

    public function getCharsetCollateDataProvider(): array
    {
        return [
            ['utf8', 'utf8_general_ci', ' DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci '],
            ['', 'utf8_general_ci', ' COLLATE utf8_general_ci '],
            ['utf8', '', ' DEFAULT CHARACTER SET utf8 '],
            ['', '', ''],
        ];
    }
}
