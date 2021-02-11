<?php

namespace Smartling\Tests\Jobs;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Smartling\ApiWrapperInterface;
use Smartling\Exception\SmartlingNetworkException;
use Smartling\Helpers\QueryBuilder\TransactionManager;
use Smartling\Jobs\LastModifiedCheckJob;
use Smartling\Queue\Queue;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;
use Smartling\Tests\Mocks\WordpressFunctionsMockHelper;
use Smartling\Tests\Traits\ApiWrapperMock;
use Smartling\Tests\Traits\DateTimeBuilder;
use Smartling\Tests\Traits\DbAlMock;
use Smartling\Tests\Traits\DummyLoggerMock;
use Smartling\Tests\Traits\EntityHelperMock;
use Smartling\Tests\Traits\InvokeMethodTrait;
use Smartling\Tests\Traits\QueueMock;
use Smartling\Tests\Traits\SettingsManagerMock;
use Smartling\Tests\Traits\SiteHelperMock;
use Smartling\Tests\Traits\SubmissionEntityMock;
use Smartling\Tests\Traits\SubmissionManagerMock;

class LastModifiedCheckJobTest extends TestCase
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

    use InvokeMethodTrait;
    private $settingsManager;
    private $lastModifiedWorker;
    private $submissionManager;
    private $apiWrapper;
    private $queue;

    protected function setUp(): void
    {
        $dbMock = $this->mockDbAl();

        $this->submissionManager = $this->mockSubmissionManager(
            $dbMock,
            $this->mockEntityHelper($this->mockSiteHelper())
        );

        $this->settingsManager = $this->getSettingsManagerMock();

        $this->queue = $this->mockQueue();

        $this->apiWrapper = $this->getApiWrapperMock();

        $this->lastModifiedWorker = $this->getWorkerMock(
            $this->submissionManager,
            $this->apiWrapper,
            $this->queue
        );
    }

    /**
     * @param SubmissionManager $submissionManager
     * @param ApiWrapperInterface $apiWrapper
     * @param Queue $queue
     *
     * @return MockObject|LastModifiedCheckJob
     */
    private function getWorkerMock(SubmissionManager $submissionManager, ApiWrapperInterface $apiWrapper, Queue $queue)
    {
        $transactionManager = $this->getMockBuilder(TransactionManager::class)
            ->setConstructorArgs([$this->mockDbAl()])
            ->onlyMethods(['executeSelectForUpdate'])
            ->getMock();
        $worker = $this->getMockBuilder(LastModifiedCheckJob::class)
            ->onlyMethods(['prepareSubmissionList'])
            ->setConstructorArgs([$submissionManager, $transactionManager])
            ->getMock();

        $worker->setApiWrapper($apiWrapper);
        $worker->setQueue($queue);

        return $worker;
    }

    /**
     * @dataProvider runDataProvider
     *
     * @param array $groupedSubmissions
     * @param array $lastModifiedResponse
     * @param int $expectedStatusCheckRequests
     */
    public function testRun(array $groupedSubmissions, array $lastModifiedResponse, int $expectedStatusCheckRequests)
    {
        $worker = $this->lastModifiedWorker;

        foreach ($groupedSubmissions as $index => $mockedResult) {
            $dequeueResult = $this->mockedResultToDequeueResult($groupedSubmissions);

            $this->queue
                ->expects(self::at($index))
                ->method('dequeue')
                ->with(Queue::QUEUE_NAME_LAST_MODIFIED_CHECK_QUEUE)
                ->willReturn($dequeueResult[$index]);

            if (false !== $mockedResult) {
                foreach ($mockedResult as $fileUri => $submissionList) {

                    $dequeued = &$dequeueResult[$index][$fileUri];

                    foreach ($submissionList as & $submissionArray) {
                        $submissionArray = SubmissionEntity::fromArray($submissionArray, $this->getLogger());
                    }
                    unset ($submissionArray);

                    $this->submissionManager
                        ->method('findByIds')
                        ->with($dequeued)
                        ->willReturn($submissionList);

                    $unserializedSubmissions = $this->submissionManager->findByIds($dequeued);

                    $worker
                        ->method('prepareSubmissionList')
                        ->with($unserializedSubmissions)
                        ->willReturn($this->emulatePrepareSubmissionList($unserializedSubmissions));

                    $this->apiWrapper
                        ->expects(self::once())
                        ->method('lastModified')
                        ->with(reset($unserializedSubmissions))
                        ->willReturn($lastModifiedResponse);
                }
            }
        }

        $this->apiWrapper
            ->expects(self::exactly($expectedStatusCheckRequests))
            ->method('getStatusForAllLocales')
            ->withAnyParameters()
            ->will(self::returnArgument(0));

        // emulate Saving
        $this->submissionManager
            ->method('storeSubmissions')
            ->will(self::returnArgument(0));

        $sm = $this->settingsManager;
        $sm->method('getSingleSettingsProfile')->willReturn(false);

        $worker->setSettingsManager($sm);

        $worker->run();
    }

    private function mockedResultToDequeueResult(array $mocked): array
    {
        $rebuiltArray = [];

        foreach ($mocked as $index => $mockedSet) {
            if (false === $mockedSet) {
                $rebuiltArray[$index] = $mockedSet;
                continue;
            }
            foreach ($mockedSet as $fileUri => $serializedSubmissions) {
                foreach ($serializedSubmissions as $serializedSubmission) {
                    $rebuiltArray[$index][$fileUri][] = $serializedSubmission['id'];
                }
            }
        }

        return $rebuiltArray;
    }

    /**
     * @param SubmissionEntity[] $submissions
     *
     * @return array
     */
    public function emulatePrepareSubmissionList(array $submissions): array
    {
        $output = [];

        foreach ($submissions as $submissionEntity) {
            $smartlingLocaleId = $submissionEntity->getTargetLocale();
            $output[$smartlingLocaleId] = $submissionEntity;
        }

        return $output;
    }

    public function runDataProvider(): array
    {
        WordpressFunctionsMockHelper::injectFunctionsMocks();
        return [
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
                [
                    'LangA' => $this->mkDateTime('2016-01-10 00:00:00'),
                    'LangB' => $this->mkDateTime('2016-01-10 00:00:00'),
                ],
                0,
            ],
        ];
    }

    /**
     * @dataProvider filterSubmissionsDataProvider
     * @param array $lastModifiedResponse
     * @param array $submissions
     * @param array $expectedFilteredResult
     */
    public function testFilterSubmissions(array $lastModifiedResponse, array $submissions, array $expectedFilteredResult)
    {
        $actualResult = $this->invokeMethod(
            $this->lastModifiedWorker,
            'filterSubmissions',
            [
                $lastModifiedResponse,
                $submissions,
            ]
        );

        self::assertEquals(
            $this->serializeSubmissions($expectedFilteredResult),
            $this->serializeSubmissions($actualResult)
        );
    }

    protected function serializeSubmissions(array $submissions): array
    {
        $rebuild = [];
        foreach ($submissions as $key => $submission) {
            $rebuild[$key] = $submission->toArray();
        }

        return $rebuild;
    }

    public function filterSubmissionsDataProvider(): array
    {
        return [
            [
                [
                    'LangA' => $this->mkDateTime('2016-01-01 10:00:01'),
                    'LangB' => $this->mkDateTime('2016-01-01 10:00:02'),
                    'LangC' => $this->mkDateTime('2016-01-01 10:00:03'),
                ],
                [
                    'LangA' => SubmissionEntity::fromArray($this->getSerializedSubmission('FileA', 'LangA'), $this->getLogger()),
                    'LangB' => SubmissionEntity::fromArray($this->getSerializedSubmission('FileA', 'LangB'), $this->getLogger()),
                ],
                [
                    'LangA' => SubmissionEntity::fromArray($this->getSerializedSubmission('FileA', 'LangA', $this->mkDateTime('2016-01-01 10:00:01')), $this->getLogger()),
                    'LangB' => SubmissionEntity::fromArray($this->getSerializedSubmission('FileA', 'LangB', $this->mkDateTime('2016-01-01 10:00:02')), $this->getLogger()),
                ],
            ],
            [
                [
                    'LangB' => $this->mkDateTime('2016-01-01 10:00:02'),
                    'LangC' => $this->mkDateTime('2016-01-01 10:00:03'),
                ],
                [
                    'LangA' => SubmissionEntity::fromArray($this->getSerializedSubmission('FileA', 'LangA'), $this->getLogger()),
                    'LangB' => SubmissionEntity::fromArray($this->getSerializedSubmission('FileA', 'LangB'), $this->getLogger()),
                ],
                [
                    'LangB' => SubmissionEntity::fromArray($this->getSerializedSubmission('FileA', 'LangB', $this->mkDateTime('2016-01-01 10:00:02')), $this->getLogger()),
                ],
            ],
            [
                [
                    'LangC' => $this->mkDateTime('2016-01-01 10:00:03'),
                ],
                [
                    'LangA' => SubmissionEntity::fromArray($this->getSerializedSubmission('FileA', 'LangA'), $this->getLogger()),
                    'LangB' => SubmissionEntity::fromArray($this->getSerializedSubmission('FileA', 'LangB'), $this->getLogger()),
                ],
                [
                ],
            ],
        ];
    }

    /**
     * @param array $inputData
     * @param bool  $expectedResult
     * @dataProvider validateSerializedPairDataProvider
     */
    public function testValidateSerializedPair(array $inputData, bool $expectedResult)
    {
        self::assertEquals(
            $expectedResult,
            $this->invokeMethod(
                $this->lastModifiedWorker,
                'validateSerializedPair',
                [$inputData]
            ),
            vsprintf('Expected %s with input params %s', [var_export($expectedResult, true),
                                                          var_export($inputData, true)])
        );
    }

    public function validateSerializedPairDataProvider(): array
    {
        return [
            [
                ['file.xml' => [1, 2, 3]],
                true,
            ],
            [
                [null => [1, 2, 3]],
                false,
            ],
            [
                ['file.xml' => [null, 2, 3]],
                false,
            ],
            [
                ['file.xml' => null],
                false,
            ],
            [
                ['file.xml' => []],
                false,
            ],
            [
                ['file.xml' => [1, '2d', 3]],
                false,
            ],
            [
                ['file.xml' => [1, 'd2', 3]],
                false,
            ],
            [
                ['file.xml' => [1, (object)['f'], 3]],
                false,
            ],
            [
                ['file.xml' => 3],
                false,
            ],
        ];
    }

    /**
     * @param array $groupedSubmissions
     * @param string $exceptionMessage
     * @param int $storeEntityCount
     * @dataProvider failLastModifiedDataProvider
     */
    public function testFailLastModified(array $groupedSubmissions, string $exceptionMessage, int $storeEntityCount)
    {
        $worker = $this->lastModifiedWorker;

        foreach ($groupedSubmissions as $index => $mockedResult) {
            $dequeueResult = $this->mockedResultToDequeueResult($groupedSubmissions);

            $this->queue
                ->expects(self::at($index))
                ->method('dequeue')
                ->with(Queue::QUEUE_NAME_LAST_MODIFIED_CHECK_QUEUE)
                ->willReturn($dequeueResult[$index]);

            if (false !== $mockedResult) {
                foreach ($mockedResult as $fileUri => $submissionList) {

                    $dequeued = $dequeueResult[$index][$fileUri];

                    foreach ($submissionList as & $submissionArray) {
                        $submissionArray = SubmissionEntity::fromArray($submissionArray, $this->getLogger());
                    }
                    unset ($submissionArray);

                    $this->submissionManager
                        ->method('findByIds')
                        ->with($dequeued)
                        ->willReturn($submissionList);

                    $unserializedSubmissions = $this->submissionManager->findByIds($dequeued);

                    $worker
                        ->method('prepareSubmissionList')
                        ->with($unserializedSubmissions)
                        ->willReturn($this->emulatePrepareSubmissionList($unserializedSubmissions));

                    $this->apiWrapper
                        ->expects(self::once())
                        ->method('lastModified')
                        ->with(reset($unserializedSubmissions))
                        ->willThrowException(new SmartlingNetworkException($exceptionMessage));
                }
            }
        }

        $this->apiWrapper
            ->expects(self::never())
            ->method('getStatusForAllLocales');

        $this->submissionManager
            ->expects(self::never())
            ->method('storeSubmissions');

        $this->submissionManager
            ->expects(self::exactly($storeEntityCount))
            ->method('storeEntity');

        $sm = $this->settingsManager;
        $sm->method('getSingleSettingsProfile')->willReturn(false);

        $worker->setSettingsManager($sm);

        $worker->run();
    }

    public function failLastModifiedDataProvider(): array
    {
        WordpressFunctionsMockHelper::injectFunctionsMocks();
        return [
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
                'Array
(
    [0] => Array
        (
            [key] => file.not.found
            [message] => The file "FileA" could not be found
            [details] => Array
                (
                    [field] => fileUri
                )
        )
)',
                1, // store failed status on submission
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
                'Array
(
    [0] => Array
        (
            [key] => some.key
            [message] => The file "FileA" could not be found
            [details] => Array
                (
                    [field] => fileUri
                )
        )
)',
                0, // Don't store failed status on submission
            ],
        ];
    }
}
