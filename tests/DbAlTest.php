<?php

namespace Smartling\Tests;

use PHPUnit\Framework\TestCase;
use Smartling\DbAl\DB;
use Smartling\DbAl\Migrations\DbMigrationManager;
use Smartling\Tests\Traits\InvokeMethodTrait;

class DbAlTest extends TestCase
{
    /**
     * @dataProvider getCharsetCollateDataProvider
     */
    public function testGetCharsetCollate(string $charset, string $collate, string $expectedResult)
    {
        $wpdb = new class($charset, $collate) {
            public string $base_prefix = '';
            public function __construct(public string $charset, public string $collate)
            {
            }
        };

        $result = (new DB($wpdb))->prepareSql([
            'columns' => [],
            'indexes' => [],
            'name' => '',
        ]);

        $this->assertStringContainsString($expectedResult, $result);
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
