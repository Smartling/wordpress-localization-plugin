<?php

namespace Queue;


use Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapperInterface;
use Smartling\Jobs\SubmissionCollectorJob;
use Smartling\Queue\QueueInterface;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;
use Smartling\Tests\Traits\DbAlMock;
use Smartling\Tests\Traits\DummyLoggerMock;
use Smartling\Tests\Traits\EntityHelperMock;
use Smartling\Tests\Traits\QueueMock;
use Smartling\Tests\Traits\SiteHelperMock;
use Smartling\Tests\Traits\SubmissionEntityMock;
use Smartling\Tests\Traits\SubmissionManagerMock;

/**
 * @inheritdoc
 * Class SubmissionCollectorJobTest
 * @package Queue
 * @covers  \Smartling\Jobs\LastModifiedCheckJob
 */
class SubmissionCollectorJobTest extends \PHPUnit_Framework_TestCase
{

    use DummyLoggerMock;
    use DbAlMock;
    use EntityHelperMock;
    use SiteHelperMock;
    use SubmissionManagerMock;
    use SubmissionEntityMock;
    use QueueMock;

    /**
     * @var SmartlingToCMSDatabaseAccessWrapperInterface | \PHPUnit_Framework_MockObject_MockObject
     */
    private $dbal;

    /**
     * @var SubmissionManager | \PHPUnit_Framework_MockObject_MockObject
     */
    private $submissionManager;

    /**
     * @var QueueInterface | \PHPUnit_Framework_MockObject_MockObject
     */
    private $queue;

    /**
     * @var SubmissionCollectorJob
     */
    private $submissionCollector;

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|SmartlingToCMSDatabaseAccessWrapperInterface
     */
    public function getDbal()
    {
        return $this->dbal;
    }

    /**
     * @param \PHPUnit_Framework_MockObject_MockObject|SmartlingToCMSDatabaseAccessWrapperInterface $dbal
     */
    public function setDbal($dbal)
    {
        $this->dbal = $dbal;
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|SubmissionManager
     */
    public function getSubmissionManager()
    {
        return $this->submissionManager;
    }

    /**
     * @param \PHPUnit_Framework_MockObject_MockObject|SubmissionManager $submissionManager
     */
    public function setSubmissionManager($submissionManager)
    {
        $this->submissionManager = $submissionManager;
    }


    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|QueueInterface
     */
    public function getQueue()
    {
        return $this->queue;
    }

    /**
     * @param \PHPUnit_Framework_MockObject_MockObject|QueueInterface $queue
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


    protected function setUp()
    {

        $this->setDbal($this->mockDbAl());

        $submissionManager = $this->mockSubmissionManager(
            $this->getLogger(),
            $this->getDbal(),
            $this->mockEntityHelper($this->getLogger(), $this->mockSiteHelper($this->getLogger()))
        );

        $this->setSubmissionManager($submissionManager);

        $job = new SubmissionCollectorJob(
            $this->getLogger(),
            $this->getSubmissionManager()
        );

        $this->setQueue($this->mockQueue());

        $job->setQueue($this->getQueue());

        $this->setSubmissionCollector($job);
    }

    /**
     * @param SubmissionEntity[] $submissions
     * @param array              $expected
     *
     * @covers       \Smartling\Jobs\LastModifiedCheckJob::run()
     * @dataProvider runDataProvider
     */
    public function testRun(array $submissions, array $expected)
    {
        $submissionManager = $this->getSubmissionManager();

        // ensure correct search params
        $submissionManager
            ->expects(self::any())
            ->method('find')
            ->with(
                [
                    'status' => [
                        SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS,
                        SubmissionEntity::SUBMISSION_STATUS_COMPLETED,
                    ],
                ])
            ->willReturn($submissions);

        $submissionManager->expects(self::any())->method('filterBrokenSubmissions')->with($submissions)->willReturnArgument(0);

        foreach ($expected as $index => $set) {
            $this->getQueue()->expects(self::at($index))->method('enqueue')->with($set);
        }


        $this->getSubmissionCollector()->run();
    }

    /**
     * @return array
     */
    public function runDataProvider()
    {
        return [
            [
                [
                    SubmissionEntity::fromArray($this->getSerializedSubmission('/file_a.xml', 'es', new \DateTime('now'), 10, 1), $this->getLogger()),
                    SubmissionEntity::fromArray($this->getSerializedSubmission('/file_a.xml', 'fr', new \DateTime('now'), 20, 2), $this->getLogger()),
                    SubmissionEntity::fromArray($this->getSerializedSubmission('/file_a.xml', 'pl', new \DateTime('now'), 30, 3), $this->getLogger()),
                    SubmissionEntity::fromArray($this->getSerializedSubmission('/file_a.xml', 'cn', new \DateTime('now'), 40, 4), $this->getLogger()),
                ],
                [
                    ['/file_a.xml' => [1, 2, 3, 4]],
                ],
            ],
            [
                [
                    SubmissionEntity::fromArray($this->getSerializedSubmission('/file_b.xml', 'es', new \DateTime('now'), 10, 5), $this->getLogger()),
                    SubmissionEntity::fromArray($this->getSerializedSubmission('/file_b.xml', 'fr', new \DateTime('now'), 20, 6), $this->getLogger()),
                    SubmissionEntity::fromArray($this->getSerializedSubmission('/file_b.xml', 'pl', new \DateTime('now'), 30, 7), $this->getLogger()),
                    SubmissionEntity::fromArray($this->getSerializedSubmission('/file_b.xml', 'cn', new \DateTime('now'), 40, 8), $this->getLogger()),
                ],
                [
                    ['/file_b.xml' => [5, 6, 7, 8]],
                ],
            ],
            [
                [
                    SubmissionEntity::fromArray($this->getSerializedSubmission('/file_a.xml', 'es', new \DateTime('now'), 10, 9), $this->getLogger()),
                    SubmissionEntity::fromArray($this->getSerializedSubmission('/file_b.xml', 'fr', new \DateTime('now'), 20, 10), $this->getLogger()),
                    SubmissionEntity::fromArray($this->getSerializedSubmission('/file_c.xml', 'pl', new \DateTime('now'), 30, 11), $this->getLogger()),

                ],
                [
                    [
                        '/file_a.xml' => [9],
                    ],
                    [
                        '/file_b.xml' => [10],
                    ],
                    [
                        '/file_c.xml' => [11],
                    ],
                ],
            ],
        ];
    }

}