<?php

namespace Smartling\Tests\Traits;

use PHPUnit\Framework\MockObject\MockObject;
use Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapperInterface;
use Smartling\Helpers\EntityHelper;
use Smartling\Submissions\SubmissionManager;

trait SubmissionManagerMock
{
    /**
     * @param SmartlingToCMSDatabaseAccessWrapperInterface $dbal
     * @param EntityHelper $entityHelper
     *
     * @return MockObject|SubmissionManager
     */
    private function mockSubmissionManager(SmartlingToCMSDatabaseAccessWrapperInterface $dbal, EntityHelper $entityHelper)
    {
        return $this->getMockBuilder(SubmissionManager::class)
            ->onlyMethods(
                [
                    'find',
                    'findByIds',
                    'storeEntity',
                    'setErrorMessage',
                    'storeSubmissions',
                ]
            )
            ->setConstructorArgs([$dbal, 10, $entityHelper])
            ->getMock();
    }
}
