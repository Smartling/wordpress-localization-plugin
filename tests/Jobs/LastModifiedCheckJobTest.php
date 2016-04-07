<?php

namespace Smartling\Tests\Jobs;

use Smartling\ApiWrapperInterface;
use Smartling\Jobs\LastModifiedCheckJob;
use Smartling\Queue\Queue;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;
use Smartling\Tests\Traits\ApiWrapperMock;
use Smartling\Tests\Traits\DateTimeBuilder;
use Smartling\Tests\Traits\DbAlMock;
use Smartling\Tests\Traits\DummyLoggerMock;
use Smartling\Tests\Traits\EntityHelperMock;
use Smartling\Tests\Traits\QueueMock;
use Smartling\Tests\Traits\SettingsManagerMock;
use Smartling\Tests\Traits\SiteHelperMock;
use Smartling\Tests\Traits\SubmissionEntityMock;
use Smartling\Tests\Traits\SubmissionManagerMock;

/**
 * Class LastModifiedCheckJobTest
 * @package Jobs
 * @covers  \Smartling\Jobs\LastModifiedCheckJob
 */
class LastModifiedCheckJobTest extends \PHPUnit_Framework_TestCase
{
    use DummyLoggerMock;
    use DateTimeBuilder;
    use DbAlMock;
    use SiteHelperMock;
    use EntityHelperMock;
    use SubmissionEntityMock;
    use SubmissionManagerMock;
    use SettingsManagerMock;
    use QueueMock;
    use ApiWrapperMock;

    /**
     * @var SettingsManager||\PHPUnit_Framework_MockObject_MockObject
     */
    private $settingsManager;

    /**
     * @var LastModifiedCheckJob|\PHPUnit_Framework_MockObject_MockObject
     */
    private $lastModifiedWorker;

    /**
     * @var SubmissionManager|\PHPUnit_Framework_MockObject_MockObject
     */
    private $submissionManager;

    /**
     * @var ApiWrapperInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $apiWrapper;

    /**
     * @var Queue|\PHPUnit_Framework_MockObject_MockObject
     */
    private $queue;

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
     * @return \PHPUnit_Framework_MockObject_MockObject|Queue
     */
    public function getQueue()
    {
        return $this->queue;
    }

    /**
     * @param \PHPUnit_Framework_MockObject_MockObject|Queue $queue
     */
    public function setQueue($queue)
    {
        $this->queue = $queue;
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|ApiWrapperInterface
     */
    public function getApiWrapper()
    {
        return $this->apiWrapper;
    }

    /**
     * @param \PHPUnit_Framework_MockObject_MockObject|ApiWrapperInterface $apiWrapper
     */
    public function setApiWrapper($apiWrapper)
    {
        $this->apiWrapper = $apiWrapper;
    }

    /**
     * @return LastModifiedCheckJob
     */
    public function getLastModifiedWorker()
    {
        return $this->lastModifiedWorker;
    }

    /**
     * @param LastModifiedCheckJob $lastModifiedWorker
     */
    public function setLastModifiedWorker($lastModifiedWorker)
    {
        $this->lastModifiedWorker = $lastModifiedWorker;
    }

    /**
     * @return SettingsManager
     */
    public function getSettingsManager()
    {
        return $this->settingsManager;
    }

    /**
     * @param SettingsManager $settingsManager
     */
    public function setSettingsManager($settingsManager)
    {
        $this->settingsManager = $settingsManager;
    }


    /**
     * Sets up the fixture, for example, open a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        $dbMock = $this->mockDbAl();

        $this->setSubmissionManager(
            $this->mockSubmissionManager(
                $this->getLogger(), $dbMock,
                $this->mockEntityHelper($this->getLogger(), $this->mockSiteHelper($this->getLogger()))
            )
        );

        $this->setSettingsManager($this->getSettingsManagerMock());

        $this->setQueue($this->mockQueue());

        $this->setApiWrapper($this->getApiWrapperMock());
    }


    /**
     * @covers       Smartling\Jobs\LastModifiedCheckJob::run()
     * @dataProvider runDataProvider
     *
     * @param array            $groupedSubmissions
     * @param SubmissionEntity $submission
     * @param array            $lastModifiedResponse
     * @param int              $expectedStatusCheckRequests
     */
    public function testRun(array $groupedSubmissions, SubmissionEntity $submission, array $lastModifiedResponse, $expectedStatusCheckRequests)
    {

        $worker = $this->getMockBuilder('Smartling\Jobs\LastModifiedCheckJob')
            ->setMethods(['prepareSubmissionList'])
            ->setConstructorArgs([$this->getLogger(), $this->getSubmissionManager()])
            ->getMock();

        $worker->setApiWrapper($this->getApiWrapper());
        $worker->setQueue($this->getQueue());

        foreach ($groupedSubmissions as $index => $mockedResult) {
            $this->getQueue()
                ->expects(self::at($index))
                ->method('dequeue')
                ->with(Queue::QUEUE_NAME_LAST_MODIFIED_CHECK_QUEUE)
                ->willReturn($mockedResult);

            if (false !== $mockedResult) {
                foreach ($mockedResult as $fileUri => $submissionList) {
                    $unserializedSubmissions = $this->getSubmissionManager()->unserializeSubmissions($submissionList);

                    $worker->expects(self::any())
                        ->method('prepareSubmissionList')
                        ->with($unserializedSubmissions)
                        ->willReturn($this->emulatePrepareSubmissionList($unserializedSubmissions));
                }
            }
        }

        $this->getApiWrapper()
            ->expects(self::exactly(1))
            ->method('lastModified')
            ->with($submission)
            ->willReturn($lastModifiedResponse);

        $this->getApiWrapper()
            ->expects(self::exactly($expectedStatusCheckRequests))
            ->method('getStatusForAllLocales')
            ->withAnyParameters()
            ->will(self::returnArgument(0));

        // emulate Saving
        $this->getSubmissionManager()
            ->expects(self::any())
            ->method('storeSubmissions')
            ->will(self::returnArgument(0));

        $worker->run();
    }

    /**
     * @param SubmissionEntity[] $submissions
     *
     * @return array
     */
    public function emulatePrepareSubmissionList($submissions)
    {
        $output = [];

        foreach ($submissions as $submissionEntity) {
            $smartlingLocaleId = $submissionEntity->getTargetLocale();
            $output[$smartlingLocaleId] = $submissionEntity;
        }

        return $output;
    }

    /**
     * Data Provider for testRun test
     */
    public function runDataProvider()
    {
        return [
            [
                [
                    [
                        'FileA' =>
                            [
                                $this->getSerializedSubmission('FileA', 'LangA'),
                                $this->getSerializedSubmission('FileA', 'LangB'),
                            ],

                    ],
                    false,
                ],
                SubmissionEntity::fromArray($this->getSerializedSubmission('FileA', 'LangA'), $this->getLogger()),
                [
                    'LangA' => $this->mkDateTime('2016-01-10 00:00:00'),
                    'LangB' => $this->mkDateTime('2016-01-10 00:14:00'),
                ],
                1,
            ],
            [
                [
                    [
                        'FileA' =>
                            [
                                $this->getSerializedSubmission('FileA', 'LangA', $this->mkDateTime('2016-01-10 00:00:00')),
                                $this->getSerializedSubmission('FileA', 'LangB', $this->mkDateTime('2016-01-10 00:00:00')),
                            ],

                    ],
                    false,
                ],
                SubmissionEntity::fromArray($this->getSerializedSubmission('FileA', 'LangA', $this->mkDateTime('2016-01-10 00:00:00')), $this->getLogger()),
                [
                    'LangA' => $this->mkDateTime('2016-01-10 00:00:00'),
                    'LangB' => $this->mkDateTime('2016-01-10 00:00:00'),
                ],
                0,
            ],
        ];
    }

    
}