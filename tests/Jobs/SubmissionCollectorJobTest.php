<?php

namespace Jobs;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapperInterface;
use Smartling\Helpers\EntityHelper;
use Smartling\Helpers\SiteHelper;
use Smartling\Jobs\SubmissionCollectorJob;
use Smartling\Queue\Queue;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;
use Smartling\Tests\Traits\InvokeMethodTrait;

/**
 * Class SubmissionCollectorJobTest
 * @package Jobs
 * @covers  \Smartling\Jobs\SubmissionCollectorJob
 */
class SubmissionCollectorJobTest extends \PHPUnit_Framework_TestCase
{
    use InvokeMethodTrait;

    //region Fields Definitions
    /**
     * @var LoggerInterface
     */
    private $logger;

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
     * @return LoggerInterface
     */
    public function getLogger()
    {
        if (!($this->logger instanceof LoggerInterface)) {
            $this->setLogger(new NullLogger());
        }

        return $this->logger;
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger($logger)
    {
        $this->logger = $logger;
    }

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

    /**
     * @param LoggerInterface $logger
     *
     * @return \PHPUnit_Framework_MockObject_MockObject|\Smartling\Helpers\SiteHelper
     */
    private function mockSiteHelper(LoggerInterface $logger)
    {
        return $this->getMockBuilder('Smartling\Helpers\SiteHelper')
            ->setConstructorArgs([$logger])
            ->getMock();
    }

    /**
     * @param LoggerInterface $logger
     * @param SiteHelper      $siteHelper
     *
     * @return \PHPUnit_Framework_MockObject_MockObject|\Smartling\Helpers\EntityHelper
     */
    private function mockEntityHelper(LoggerInterface $logger, SiteHelper $siteHelper)
    {
        $entityHelper = $this->getMockBuilder('Smartling\Helpers\EntityHelper')
            ->setMethods(['getSiteHelper'])
            ->getMock();
        $entityHelper->setLogger($logger);

        $entityHelper->expects(self::any())
            ->method('getSiteHelper')
            ->willReturn($siteHelper);

        return $entityHelper;
    }

    /**
     * @param LoggerInterface                              $logger
     * @param SmartlingToCMSDatabaseAccessWrapperInterface $dbal
     * @param EntityHelper                                 $entityHelper
     *
     * @return \PHPUnit_Framework_MockObject_MockObject|SubmissionManager
     */
    private function mockSubmissionManager(LoggerInterface $logger, SmartlingToCMSDatabaseAccessWrapperInterface $dbal, EntityHelper $entityHelper)
    {
        return $this->getMockBuilder('Smartling\Submissions\SubmissionManager')
            ->setMethods(['find'])
            ->setConstructorArgs([$logger, $dbal, 10, $entityHelper])
            ->getMock();
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|Queue
     */
    private function mockQueue()
    {
        return $this->getMockBuilder('Smartling\Queue\Queue')
            ->setMethods(['enqueue'])
            ->disableOriginalConstructor()
            ->getMock();
    }

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
            $serializedSubmissions = $this->getSubmissionManager()->serializeSubmissions($submissions);
            $this->getQueue()->expects($this->at($counter))->method('enqueue')
                ->with([$fileUri => $serializedSubmissions]);
            $counter++;
        }

        $this->getSubmissionCollector()->run();
    }

    /**
     * @param string $fileUri
     * @param string $locale
     *
     * @return array
     */
    private function getSerializedSubmission($fileUri, $locale)
    {
        return [
            'id'                     => 1,
            'source_title'           => 'A',
            'source_blog_id'         => 1,
            'source_content_hash'    => '',
            'content_type'           => 'post',
            'source_id'              => 7,
            'file_uri'               => $fileUri,
            'target_locale'          => $locale,
            'target_blog_id'         => 2,
            'target_id'              => null,
            'submitter'              => '',
            'submission_date'        => null,
            'applied_date'           => null,
            'approved_string_count'  => 0,
            'completed_string_count' => 0,
            'status'                 => SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS,
            'is_locked'              => 0,
            'last_modified'          => null,
        ];
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