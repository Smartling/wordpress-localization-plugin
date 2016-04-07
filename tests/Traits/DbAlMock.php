<?php

namespace Smartling\Tests\Traits;

/**
 * Class DbAlMock
 * @package Traits
 */
trait DbAlMock
{
    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|\Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapperInterface
     */
    private function mockDbAl()
    {
        return $this->getMockBuilder('Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapperInterface')
            ->setMethods(
                [
                    'needRawSqlLog',
                    'query',
                    'fetch',
                    'escape',
                    'completeTableName',
                    'getLastInsertedId',
                    'getLastErrorMessage',
                ]
            )
            ->getMock();
    }
}