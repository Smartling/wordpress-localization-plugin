<?php

namespace Smartling\Tests\Traits;

use PHPUnit\Framework\MockObject\MockObject;
use Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapperInterface;

trait DbAlMock
{
    /**
     * @return MockObject|\Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapperInterface
     */
    private function mockDbAl()
    {
        defined('ARRAY_A') || define('ARRAY_A', 'ARRAY_A');
        defined('OBJECT') || define('OBJECT', 'OBJECT');
        return $this->createPartialMock(SmartlingToCMSDatabaseAccessWrapperInterface::class, [
            'query',
            'fetch',
            'escape',
            'completeTableName',
            'completeMultisiteTableName',
            'getLastInsertedId',
            'getLastErrorMessage',
        ]);
    }
}
