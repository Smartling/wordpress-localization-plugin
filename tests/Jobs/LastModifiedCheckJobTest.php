<?php

namespace Smartling\Tests\Jobs;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Smartling\ApiWrapperInterface;
use Smartling\Exception\SmartlingNetworkException;
use Smartling\Helpers\Cache;
use Smartling\Jobs\LastModifiedCheckJob;
use Smartling\Queue\Queue;
use Smartling\Queue\QueueInterface;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;
use Smartling\Tests\Mocks\WordpressFunctionsMockHelper;
use Smartling\Tests\Traits\ApiWrapperMock;
use Smartling\Tests\Traits\DateTimeBuilder;
use Smartling\Tests\Traits\DbAlMock;
use Smartling\Tests\Traits\DummyLoggerMock;
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
    use SubmissionEntityMock;
    use SubmissionManagerMock;
    use SettingsManagerMock;
    use QueueMock;
    use ApiWrapperMock;

    use InvokeMethodTrait;

    private ApiWrapperInterface $apiWrapper;
    private LastModifiedCheckJob $lastModifiedWorker;
    private QueueInterface $queue;
    private SettingsManager $settingsManager;
    private SubmissionManager $submissionManager;
    private string $modifiedDateTimeString = '2016-01-10 00:00:00';

    protected function setUp(): void
    {
        $dbMock = $this->mockDbAl();

        $this->submissionManager = $this->mockSubmissionManager(
            $dbMock
        );

        $profile = $this->createMock(ConfigurationProfileEntity::class);

        $this->settingsManager = $this->createMock(SettingsManager::class);
        $this->settingsManager->method('getActiveProfiles')->willReturn([$profile]);

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
        return $this->getMockBuilder(LastModifiedCheckJob::class)
            ->onlyMethods(['prepareSubmissionList', 'getSmartlingLocaleIdBySubmission'])
            ->setConstructorArgs([
                $apiWrapper,
                $this->createMock(Cache::class),
                $this->settingsManager,
                $submissionManager,
                0,
                '20m',
                $queue,
            ])
            ->getMock();
    }

    /**
     * @dataProvider runDataProvider
     */
    public function testRun(array $groupedSubmissions, array $lastModifiedResponse, int $expectedStatusCheckRequests)
    {
        $worker = $this->lastModifiedWorker;

        $dequeueResult = $this->mockedResultToDequeueResult($groupedSubmissions);
        $dequeueResult[] = false;
        $matcher = $this->exactly(count($groupedSubmissions) + 1);
        $this->queue
            ->expects($matcher)
            ->method('dequeue')
            ->willReturnCallback(function (string $queue) use ($dequeueResult, $groupedSubmissions, $matcher) {
                $this->assertEquals(array_merge(
                    array_fill(0, count($groupedSubmissions), [QueueInterface::QUEUE_NAME_LAST_MODIFIED_CHECK_QUEUE]),
                    [[QueueInterface::QUEUE_NAME_LAST_MODIFIED_CHECK_AND_FAIL_QUEUE]]
                )[$matcher->getInvocationCount() - 1][0], $queue);
                return [...$dequeueResult][$matcher->getInvocationCount() - 1];
            });

        foreach ($groupedSubmissions as $index => $mockedResult) {
            if (false !== $mockedResult) {
                foreach ($mockedResult as $fileUri => $submissionList) {

                    foreach ($submissionList as &$submissionArray) {
                        $submissionArray[SubmissionEntity::FIELD_STATUS] = SubmissionEntity::SUBMISSION_STATUS_FAILED;
                        $submissionArray = SubmissionEntity::fromArray($submissionArray, $this->getLogger());
                    }
                    unset ($submissionArray);

                    $this->submissionManager
                        ->method('findByIds')
                        ->with($dequeueResult[$index][$fileUri])
                        ->willReturn($submissionList);

                    $worker
                        ->method('prepareSubmissionList')
                        ->with($submissionList)
                        ->willReturn($this->emulatePrepareSubmissionList($submissionList));

                    $this->apiWrapper
                        ->expects(self::once())
                        ->method('lastModified')
                        ->with($submissionList[0])
                        ->willReturn($lastModifiedResponse);
                }
            }
        }

        $this->apiWrapper
            ->expects(self::exactly($expectedStatusCheckRequests))
            ->method('getStatusForAllLocales')
            ->willReturnArgument(0);

        // emulate Saving
        $this->submissionManager
            ->method('storeSubmissions')
            ->willReturnArgument(0);

        $worker->run('test');
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
                                $this->getSerializedSubmission('FileA', 'LangA', $this->mkDateTime($this->modifiedDateTimeString)),
                                $this->getSerializedSubmission('FileA', 'LangB', $this->mkDateTime($this->modifiedDateTimeString)),
                            ],

                    ],
                    false,
                ],
                [
                    'LangA' => $this->mkDateTime($this->modifiedDateTimeString),
                    'LangB' => $this->mkDateTime($this->modifiedDateTimeString),
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
                false,
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

        $dequeueResult = $this->mockedResultToDequeueResult($groupedSubmissions);
        $dequeueResult[] = false;
        $matcher = $this->exactly(count($groupedSubmissions) + 1);
        $this->queue
            ->expects($matcher)
            ->method('dequeue')
            ->willReturnCallback(function (string $queue) use ($dequeueResult, $groupedSubmissions, $matcher) {
                $this->assertEquals(array_merge(
                    array_fill(0, count($groupedSubmissions), [QueueInterface::QUEUE_NAME_LAST_MODIFIED_CHECK_QUEUE]),
                    [[QueueInterface::QUEUE_NAME_LAST_MODIFIED_CHECK_AND_FAIL_QUEUE]]
                )[$matcher->getInvocationCount() - 1][0], $queue);
                return [...$dequeueResult][$matcher->getInvocationCount() - 1];
            });

        foreach ($groupedSubmissions as $index => $mockedResult) {
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
            ->method('setErrorMessage');

        $worker->run('test');
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
                                $this->getSerializedSubmission('FileA', 'LangA', $this->mkDateTime($this->modifiedDateTimeString)),
                                $this->getSerializedSubmission('FileA', 'LangB', $this->mkDateTime($this->modifiedDateTimeString)),
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
                                $this->getSerializedSubmission('FileA', 'LangA', $this->mkDateTime($this->modifiedDateTimeString)),
                                $this->getSerializedSubmission('FileA', 'LangB', $this->mkDateTime($this->modifiedDateTimeString)),
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

    public function testFailManual()
    {
        $submissions = [[
            'TestFile' => [
                $this->getSerializedSubmission('TestFile', 'TestLocaleA', $this->mkDateTime($this->modifiedDateTimeString)),
                $this->getSerializedSubmission('TestFile', 'TestLocaleB', $this->mkDateTime($this->modifiedDateTimeString)),
            ]
        ]];
        $this->apiWrapper->method('lastModified')->willReturn([
            'TestLocaleA' => $this->mkDateTime($this->modifiedDateTimeString),
            'TestLocaleB' => $this->mkDateTime($this->modifiedDateTimeString),
        ]);

        $missingSubmission = $this->createMock(SubmissionEntity::class);
        $missingSubmission->method('getTargetLocale')->willReturn('MissingLocale');
        $this->lastModifiedWorker = $this->getMockBuilder(LastModifiedCheckJob::class)
            ->onlyMethods(['prepareSubmissionList', 'getSmartlingLocaleIdBySubmission', 'placeLockFlag'])
            ->setConstructorArgs([
                $this->apiWrapper,
                $this->createMock(Cache::class),
                $this->settingsManager,
                $this->submissionManager,
                0,
                '20m',
                $this->queue,
            ])
            ->getMock();
        $this->lastModifiedWorker->method('getSmartlingLocaleIdBySubmission')->willReturn($missingSubmission->getTargetLocale());

        $dequeueResult = $this->mockedResultToDequeueResult($submissions);
        $matcher = $this->exactly(count($submissions) + 2);
        $this->queue->expects($matcher)->method('dequeue')
            ->willReturnCallback(function (string $queue) use ($matcher, $submissions, $dequeueResult) {
                $this->assertEquals(array_merge(
                    [[QueueInterface::QUEUE_NAME_LAST_MODIFIED_CHECK_QUEUE]],
                    array_fill(0, count($submissions) + 2, [QueueInterface::QUEUE_NAME_LAST_MODIFIED_CHECK_AND_FAIL_QUEUE]),
                )[$matcher->getInvocationCount() - 1][0], $queue);
                return array_merge([false], $dequeueResult, [false])[$matcher->getInvocationCount() - 1];
            });
        $submissionList = [];
        foreach ($submissions as $index => $result) {
            if (is_array($result)) {
                foreach ($result as $fileUri => $submissionList) {
                    $dequeued = $dequeueResult[$index][$fileUri];

                    foreach ($submissionList as &$submissionArray) {
                        $submissionArray = SubmissionEntity::fromArray($submissionArray, $this->getLogger());
                    }
                    unset ($submissionArray);
                    $submissionList[] = $missingSubmission;

                    $this->submissionManager->method('findByIds')->with($dequeued)->willReturn($submissionList);

                    $foundSubmissions = $this->submissionManager->findByIds($dequeued);

                    $this->lastModifiedWorker->method('prepareSubmissionList')->with($foundSubmissions)->willReturn($this->emulatePrepareSubmissionList($foundSubmissions));
                }
            }
        }

        $missingSubmission->expects($this->once())->method('setStatus')->with(SubmissionEntity::SUBMISSION_STATUS_FAILED);
        $missingSubmission->expects($this->once())->method('setLastError')->with('File submitted for locales TestLocaleA, TestLocaleB. This submission is for locale ' . $missingSubmission->getTargetLocale());

        $matcher = $this->exactly(2);
        $this->submissionManager->expects($matcher)->method('storeSubmissions')->willReturnCallback(function ($x) use ($matcher, $missingSubmission, $submissionList) {
            switch ($matcher->getInvocationCount()) {
                case 1:
                    $this->assertEquals($x, [$missingSubmission]);
                    break;
                case 2:
                    $this->assertEquals($x, ['TestLocaleA' => $submissionList[0], 'TestLocaleB' => $submissionList[1]]);
                    break;
            }
            return [];
        });

        $this->lastModifiedWorker->run('test');
    }
}
