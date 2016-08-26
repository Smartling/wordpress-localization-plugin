<?php

namespace Smartling\Tests\Jobs;

use Psr\Log\LoggerInterface;
use Smartling\Jobs\SubmissionCollectorJob;
use Smartling\Queue\Queue;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;
use Smartling\Tests\Traits\DbAlMock;
use Smartling\Tests\Traits\DummyLoggerMock;
use Smartling\Tests\Traits\EntityHelperMock;
use Smartling\Tests\Traits\InvokeMethodTrait;
use Smartling\Tests\Traits\QueueMock;
use Smartling\Tests\Traits\SiteHelperMock;
use Smartling\Tests\Traits\SubmissionEntityMock;
use Smartling\Tests\Traits\SubmissionManagerMock;

/**
 * Class SubmissionCollectorJobTest
 * @package Jobs
 * @covers  \Smartling\Jobs\SubmissionCollectorJob
 */
class SubmissionCollectorJobTest extends \PHPUnit_Framework_TestCase
{
    use InvokeMethodTrait;

    use DummyLoggerMock;
    use DbAlMock;
    use SiteHelperMock;
    use EntityHelperMock;
    use SubmissionEntityMock;
    use SubmissionManagerMock;
    use QueueMock;

    //region Fields Definitions
    /**
     * @var SubmissionManager
     */
    private $submissionManager;

    /**
     * @var SubmissionEntity
     */
    private $submissionEntity;

    /**
     * @var SubmissionCollectorJob
     */
    private $submissionCollector;

    /**
     * @var Queue
     */
    private $queue;
    
    /**
     * @return SubmissionManager|\PHPUnit_Framework_MockObject_MockObject
     */
    public function getSubmissionManager()
    {
        return $this->submissionManager;
    }

    /**
     * @param SubmissionManager $submissionManager
     */
    public function setSubmissionManager($submissionManager)
    {
        $this->submissionManager = $submissionManager;
    }

    /**
     * @return SubmissionEntity
     */
    public function getSubmissionEntity()
    {
        return $this->submissionEntity;
    }

    /**
     * @param SubmissionEntity $submissionEntity
     */
    public function setSubmissionEntity($submissionEntity)
    {
        $this->submissionEntity = $submissionEntity;
    }

    /**
     * @return Queue|\PHPUnit_Framework_MockObject_MockObject
     */
    public function getQueue()
    {
        return $this->queue;
    }

    /**
     * @param Queue $queue
     */
    public function setQueue($queue)
    {
        $this->queue = $queue;
    }

    /**
     * @return SubmissionCollectorJob
     */
    public function getSubmissionCollector()
    {
        return $this->submissionCollector;
    }

    /**
     * @param SubmissionCollectorJob $submissionCollector
     */
    public function setSubmissionCollector($submissionCollector)
    {
        $this->submissionCollector = $submissionCollector;
    }

    //endregion
    
    /**
     * @param LoggerInterface   $logger
     * @param SubmissionManager $submissionManager
     * @param Queue             $queue
     *
     * @return \PHPUnit_Framework_MockObject_MockObject|SubmissionCollectorJob
     */
    private function mockSubmissionCollector(LoggerInterface $logger, SubmissionManager $submissionManager, Queue $queue)
    {
        $submissionCollector = $this->getMockBuilder('Smartling\Jobs\SubmissionCollectorJob')
            ->setMethods(null)
            ->setConstructorArgs([$logger, $submissionManager])
            ->getMock();

        $submissionCollector->setJobRunInterval(0);
        $submissionCollector->setQueue($queue);

        return $submissionCollector;
    }


    /**
     * Sets up the fixture, for example, open a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        $logger = $this->getLogger();
        $dbalMock = $this->mockDbAl();
        $siteHelper = $this->mockSiteHelper($logger);
        $entityHelper = $this->mockEntityHelper($logger, $siteHelper);
        $this->setSubmissionManager($this->mockSubmissionManager($logger, $dbalMock, $entityHelper));
        $this->setQueue($this->mockQueue());
        $this->setSubmissionCollector($this->mockSubmissionCollector($logger, $this->getSubmissionManager(), $this->getQueue()));
    }

    /**
     * @covers       \Smartling\Jobs\SubmissionCollectorJob::groupSubmissionsByFileUri
     * @dataProvider runDataProvider
     *
     * @param array $returnSubmissions
     * @param array $expectedResult
     */
    public function testGroupSubmissionsByFileUri($returnSubmissions, $expectedResult)
    {
        $actualResult = $this->invokeMethod($this->getSubmissionCollector(), 'groupSubmissionsByFileUri', [$returnSubmissions]);
        self::assertEquals($expectedResult, $actualResult);
    }

    /**
     * @covers       \Smartling\Jobs\SubmissionCollectorJob::gatherSubmissions
     * @dataProvider runDataProvider
     *
     * @param array $returnSubmissions
     * @param array $expectedResult
     */
    public function testGatherSubmissions($returnSubmissions, $expectedResult)
    {
        $this->getSubmissionManager()->expects(self::once())
            ->method('find')
            ->with(
                [
                    'status' => [
                        SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS,
                        SubmissionEntity::SUBMISSION_STATUS_COMPLETED,
                    ],
                ]
            )
            ->willReturn($returnSubmissions);

        $actualResult = $this->invokeMethod($this->getSubmissionCollector(), 'gatherSubmissions', []);
        self::assertEquals($expectedResult, $actualResult);
    }

    /**
     * @covers       \Smartling\Jobs\SubmissionCollectorJob::run
     * @dataProvider runDataProvider
     *
     * @param array $returnSubmissions
     * @param array $expectedResult
     */
    public function testRun($returnSubmissions, $expectedResult)
    {
        $this->getSubmissionManager()->expects($this->at(0))
            ->method('find')
            ->with(
                [
                    'status' => [
                        SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS,
                        SubmissionEntity::SUBMISSION_STATUS_COMPLETED,
                    ],
                ]
            )
            ->willReturn($returnSubmissions);

        $this->getSubmissionManager()->expects($this->at(1))
            ->method('find')
            ->with(
                [
                    'status' => [
                        SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS,
                        SubmissionEntity::SUBMISSION_STATUS_COMPLETED,
                    ],
                ]
            )
            ->willReturn($returnSubmissions);

        $groupedSubmissions = $this->invokeMethod($this->getSubmissionCollector(), 'gatherSubmissions', []);

        $counter = 0;

        foreach ($groupedSubmissions as $fileUri => $submissions) {

            $submissionIds = [];

            foreach ($submissions as $submissionEntity) {
                $submissionIds[] = $submissionEntity->getId();
            }

            $this->getQueue()
                ->expects($this->at($counter))
                ->method('enqueue')
                ->with([$fileUri => $submissionIds]);
            $counter++;
        }

        $this->getSubmissionCollector()->run();
    }

    /**
     * Data Provider for testRun test
     */
    public function runDataProvider()
    {
        return [
            [
                [
                    SubmissionEntity::fromArray($this->getSerializedSubmission('FileA', 'LangA'), $this->getLogger()),
                    SubmissionEntity::fromArray($this->getSerializedSubmission('FileB', 'LangA'), $this->getLogger()),
                ],
                [
                    'FileA' => [SubmissionEntity::fromArray($this->getSerializedSubmission('FileA', 'LangA'), $this->getLogger())],
                    'FileB' => [SubmissionEntity::fromArray($this->getSerializedSubmission('FileB', 'LangA'), $this->getLogger())],
                ],
            ],
            [
                [
                    SubmissionEntity::fromArray($this->getSerializedSubmission('FileA', 'LangA'), $this->getLogger()),
                    SubmissionEntity::fromArray($this->getSerializedSubmission('FileA', 'LangB'), $this->getLogger()),
                ],
                [
                    'FileA' =>
                        [
                            SubmissionEntity::fromArray($this->getSerializedSubmission('FileA', 'LangA'), $this->getLogger()),
                            SubmissionEntity::fromArray($this->getSerializedSubmission('FileA', 'LangB'), $this->getLogger()),
                        ],

                ],
            ],
        ];
    }
}