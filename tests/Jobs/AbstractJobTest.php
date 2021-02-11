<?php

namespace Smartling\Tests\Jobs;

use PHPUnit\Framework\TestCase;
use Smartling\Helpers\QueryBuilder\TransactionManager;
use Smartling\Jobs\JobAbstract;
use Smartling\Tests\Mocks\WordpressFunctionsMockHelper;
use Smartling\Tests\Traits\DbAlMock;
use Smartling\Tests\Traits\EntityHelperMock;
use Smartling\Tests\Traits\SiteHelperMock;
use Smartling\Tests\Traits\SubmissionManagerMock;

class AbstractJobTest extends TestCase
{
    use DbAlMock;
    use EntityHelperMock;
    use SiteHelperMock;
    use SubmissionManagerMock;

    private $submissionManager;

    protected function setUp(): void
    {
        WordpressFunctionsMockHelper::injectFunctionsMocks();
        $this->submissionManager = $this->mockSubmissionManager(
            $this->mockDbAl(),
            $this->mockEntityHelper($this->mockSiteHelper())
        );
    }

    public function testRunCronJobUnknownSource()
    {
        $transactionManager = $this->getTransactionManagerWithException(new \RuntimeException('test'));
        $x = $this->getJobAbstractMock($transactionManager);

        try {
            $x->runCronJob();
            $x->runCronJob('test');
        } catch (\Exception $e) {
            $this->fail('Should not throw exceptions when source is not user');
        }
        $this->expectNotToPerformAssertions();
    }

    public function testRunCronJobUserSource()
    {
        $exception = new \RuntimeException('test');
        $transactionManager = $this->getTransactionManagerWithException($exception);

        $x = $this->getJobAbstractMock($transactionManager);

        try {
            $x->runCronJob(JobAbstract::SOURCE_USER);
            $this->fail('Should throw exception when source is user');
        } catch (\Exception $e) {
            $this->assertEquals($e, $exception);
        }
    }

    /**
     * @param \Exception $e
     * @return \PHPUnit_Framework_MockObject_MockObject|TransactionManager
     */
    private function getTransactionManagerWithException(\Exception $e)
    {
        $transactionManager = $this->getMockBuilder(TransactionManager::class)
            ->setConstructorArgs([$this->mockDbAl()])
            ->setMethods(['executeSelectForUpdate'])
            ->getMock();
        $transactionManager->method('executeSelectForUpdate')->willThrowException($e);

        return $transactionManager;
    }

    /**
     * @param TransactionManager $transactionManager
     * @return \PHPUnit_Framework_MockObject_MockObject|JobAbstract
     */
    private function getJobAbstractMock(TransactionManager $transactionManager)
    {
        $x = $this->getMockBuilder(JobAbstract::class)
            ->setConstructorArgs([
                $this->submissionManager,
                $transactionManager,
            ])
            ->setMethods(['buildSelectQuery'])
            ->getMockForAbstractClass();
        $x->method('buildSelectQuery')->willReturn("select 1");

        return $x;
    }
}
