<?php

namespace Smartling\DbAl;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Smartling\ApiWrapperInterface;
use Smartling\Exception\SmartlingDbException;
use Smartling\Models\IntegerIterator;
use Smartling\Models\UploadQueueEntity;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;
use Smartling\Tests\Traits\DbAlMock;

class UploadQueueManagerTest extends TestCase {
    use DbAlMock;

    public function testEnqueue()
    {
        $submission1 = $this->createMock(SubmissionEntity::class);
        $submission1->method('getId')->willReturn(1);
        $submission1->method('getSourceId')->willReturn(1);
        $submission2 = $this->createMock(SubmissionEntity::class);
        $submission2->method('getId')->willReturn(2);
        $submission2->method('getSourceId')->willReturn(1);
        $submission3 = $this->createMock(SubmissionEntity::class);
        $submission3->method('getId')->willReturn(3);
        $submission3->method('getSourceId')->willReturn(2);
        $submission4 = $this->createMock(SubmissionEntity::class);
        $submission4->method('getId')->willReturn(4);
        $submission4->method('getSourceId')->willReturn(3);
        $submission5 = $this->createMock(SubmissionEntity::class); // Never gets stored
        $submission5->method('getId')->willReturn(5);
        $submission5->method('getSourceId')->willReturn(1);
        $stored = [
            $submission1,
            $submission2,
            $submission3,
            $submission4,
            $submission5,
        ];
        foreach ($stored as $submission) {
            /** @var SubmissionEntity&MockObject $submission */
            $submission->method('getContentType')->willReturn('post');
            $submission->method('getSourceBlogId')->willReturn(1);
        }

        $submissionManager = $this->createMock(SubmissionManager::class);
        $submissionManager->method('find')->willReturnCallback(function (array $arguments) use ($stored) {
            if (count($arguments) === 0) {
                return [];
            }
            $result = $stored;
            foreach ($arguments as $key => $value) {
                switch ($key) {
                    case SubmissionEntity::FIELD_SOURCE_BLOG_ID:
                        $result = array_filter($result, static function (SubmissionEntity $submission) use ($value) {
                            return $submission->getSourceBlogId() === $value;
                        });
                        break;
                    case SubmissionEntity::FIELD_SOURCE_ID:
                        $result = array_filter($result, static function (SubmissionEntity $submission) use ($value) {
                            return $submission->getSourceId() === $value;
                        });
                }
            }

            return $result;
        });
        $submissionManager->method('getEntityById')->willReturnCallback(function (int $id) use ($stored) {
            foreach ($stored as $submission) {
                assert($submission instanceof SubmissionEntity);
                if ($submission->getId() === $id) {
                    return $submission;
                }
            }
            return null;
        });

        $this->mockDbAl();
        $db = $this->getMockBuilder(DB::class)
            ->setConstructorArgs([new class {
                public string $base_prefix = '';
                public function query() {}
            }])
            ->onlyMethods(['query'])
            ->getMock();
        $matcher = $this->exactly(3);
        $db->expects($matcher)->method('query')->willReturnCallback(function ($query) use ($matcher) {
            $this->assertStringStartsWith('INSERT', $query);
            switch ($matcher->getInvocationCount()) {
                case 1:
                    $this->assertStringContainsString("'1,2'", $query, 'Expected first query to save submissions 1 and 2 (they both refer to same content)');
                    return true;
                case 2:
                    $this->assertStringContainsString("'3'", $query, 'Expected second query to save submission 3');
                    return true;
                case 3:
                    $this->assertStringContainsString("'4'", $query, 'Expected third query to save submission 4');
                    return true;
            }

            $this->fail('Expected three calls');
        });

        (new UploadQueueManager(
            $this->createMock(ApiWrapperInterface::class),
            $this->createMock(SettingsManager::class),
            $db,
            $submissionManager,
        ))->enqueue(new IntegerIterator([1, 2, 3, 4, 7]), ''); // Submission with id 7 does not exist, and should not be stored
    }

    public function testDequeue()
    {
        $submission1 = $this->createMock(SubmissionEntity::class);
        $submission1->method('getId')->willReturn(1);
        $submission1->method('getSourceId')->willReturn(1);
        $submission1->method('getSourceBlogId')->willReturn(1);
        $submission2 = $this->createMock(SubmissionEntity::class);
        $submission2->method('getId')->willReturn(2);
        $submission2->method('getSourceId')->willReturn(1);
        $submission2->method('getSourceBlogId')->willReturn(1);
        $submission3 = $this->createMock(SubmissionEntity::class);
        $submission3->method('getId')->willReturn(3);
        $submission3->method('getSourceId')->willReturn(2);
        $submission3->method('getSourceBlogId')->willReturn(2);
        $submission4 = $this->createMock(SubmissionEntity::class);
        $submission4->method('getId')->willReturn(4);
        $submission4->method('getSourceId')->willReturn(3);
        $submission4->method('getSourceBlogId')->willReturn(1);
        $stored = [
            $submission1,
            $submission2,
            $submission3,
            $submission4,
        ];

        $this->mockDbAl();
        $db = $this->getMockBuilder(DB::class)
            ->setConstructorArgs([new class {
                public string $base_prefix = '';
                public function getRowArray() {}
                public function query() {}
            }])
            ->onlyMethods(['getRowArray', 'query'])
            ->getMock();

        $matcherGetRowArray = $this->exactly(3);
        $db->expects($matcherGetRowArray)->method('getRowArray')->willReturnCallback(function ($query) use ($matcherGetRowArray) {
            $this->assertStringContainsString(
                'from smartling_upload_queue q left join smartling_submissions s',
                $query,
            );
            $this->assertStringContainsString('where s.source_blog_id = 1', $query);

            return match ($matcherGetRowArray->getInvocationCount()) {
                1 => ['id' => 1, 'batch_uid' => '', 'submission_ids' => '1,2'],
                2 => ['id' => 4, 'batch_uid' => '', 'submission_ids' => '4'],
                3 => null,
            };
        });
        $db->expects($this->exactly(2))->method('query')->willReturnCallback(function ($query) {
            $this->assertStringStartsWith('UPDATE', $query);
            return true;
        });

        $submissionManager = $this->createMock(SubmissionManager::class);
        $submissionManager->method('getEntityById')->willReturnCallback(function ($id) use ($stored) {
            foreach ($stored as $submission) {
                if ($submission->getId() === $id) {
                    return $submission;
                }
            }
            return null;
        });

        $uploadQueueManager = new UploadQueueManager(
            $this->createMock(ApiWrapperInterface::class),
            $this->createMock(SettingsManager::class),
            $db,
            $submissionManager,
        );

        $item = $uploadQueueManager->dequeue(1);
        $submissions = $item->getSubmissions();
        $this->assertCount(2, $submissions);
        $this->assertEquals(1, $submissions[0]->getId());
        $this->assertEquals(2, $submissions[1]->getId());

        $item = $uploadQueueManager->dequeue(1);
        $submissions = $item->getSubmissions();
        $this->assertCount(1, $submissions);
        $this->assertEquals(4, $submissions[0]->getId());

        $this->assertNull($uploadQueueManager->dequeue(1));
    }

    public function testDequeueClaimsRowInsteadOfDeletingIt()
    {
        $queries = [];
        $manager = $this->buildManager(
            [['id' => 7, 'batch_uid' => '', 'submission_ids' => '1', 'claimed' => null, 'attempts' => 0], null],
            [1 => 1],
            $queries,
        );

        $item = $manager->dequeue(1);

        $this->assertNotNull($item, 'Expected an unclaimed row to be dequeued');
        $this->assertCount(1, $queries, 'Expected exactly one write while claiming a row');
        $this->assertStringStartsWith('UPDATE', $queries[0], 'Dequeue must claim the row, not delete it');
        $this->assertStringContainsString(UploadQueueEntity::FIELD_CLAIMED, $queries[0]);
        $this->assertStringNotContainsStringIgnoringCase('DELETE', $queries[0]);
    }

    public function testDequeueOnlyConsidersUnclaimedOrStaleRows()
    {
        $queries = [];
        $selects = [];
        $manager = $this->buildManager(
            [['id' => 7, 'batch_uid' => '', 'submission_ids' => '1', 'claimed' => null, 'attempts' => 0], null],
            [1 => 1],
            $queries,
            $selects,
        );

        $manager->dequeue(1);

        $this->assertStringContainsString(UploadQueueEntity::FIELD_CLAIMED, $selects[0]);
        $this->assertStringContainsString(
            'is null',
            strtolower($selects[0]),
            'Expected unclaimed rows to be eligible',
        );
        $this->assertMatchesRegularExpression(
            '/claimed`? <|<.*claimed/i',
            $selects[0],
            'Expected a staleness comparison so abandoned claims are retried',
        );
    }

    /**
     * A queue row groups submissions that share the same content, so one submission
     * with an unresolvable locale takes the whole row down. Every submission that
     * still exists - the one that failed to resolve and any sibling that resolved
     * just fine - must not just vanish: each needs a visible error instead of being
     * left in New status with no queue row and no explanation.
     */
    public function testDequeueSetsErrorOnResolvedSiblingsWhenGroupIsUnprocessable()
    {
        $resolvableSubmission = $this->createMock(SubmissionEntity::class);
        $resolvableSubmission->method('getId')->willReturn(1);
        $resolvableSubmission->method('getSourceId')->willReturn(1);
        $resolvableSubmission->method('getSourceBlogId')->willReturn(1);

        $unresolvableSubmission = $this->createMock(SubmissionEntity::class);
        $unresolvableSubmission->method('getId')->willReturn(2);
        $unresolvableSubmission->method('getSourceId')->willReturn(1);
        $unresolvableSubmission->method('getSourceBlogId')->willReturn(1);
        $unresolvableSubmission->method('getTargetBlogId')->willReturn(3);

        $this->mockDbAl();
        $db = $this->getMockBuilder(DB::class)
            ->setConstructorArgs([new class {
                public string $base_prefix = '';
                public function getRowArray() {}
                public function query() {}
            }])
            ->onlyMethods(['getRowArray', 'query'])
            ->getMock();
        $db->method('getRowArray')->willReturnOnConsecutiveCalls(
            ['id' => 7, 'batch_uid' => '', 'submission_ids' => '1,2', 'claimed' => null, 'attempts' => 0],
            null,
        );
        $queries = [];
        $db->method('query')->willReturnCallback(function ($query) use (&$queries) {
            $queries[] = $query;
            return true;
        });

        $submissionManager = $this->createMock(SubmissionManager::class);
        $submissionManager->method('getEntityById')->willReturnCallback(
            function ($id) use ($resolvableSubmission, $unresolvableSubmission) {
                return match ($id) {
                    1 => $resolvableSubmission,
                    2 => $unresolvableSubmission,
                    default => null,
                };
            },
        );
        $failed = [];
        $submissionManager->method('setErrorMessage')->willReturnCallback(
            function (SubmissionEntity $submission, string $message) use (&$failed) {
                $failed[] = $submission;
                return $submission;
            },
        );

        $settingsManager = $this->createMock(SettingsManager::class);
        $settingsManager->method('getSmartlingLocaleBySubmission')->willReturnCallback(
            function (SubmissionEntity $submission) use ($resolvableSubmission) {
                if ($submission === $resolvableSubmission) {
                    return 'de-DE';
                }
                throw new SmartlingDbException('profile not found');
            },
        );

        $uploadQueueManager = new UploadQueueManager(
            $this->createMock(ApiWrapperInterface::class),
            $settingsManager,
            $db,
            $submissionManager,
        );

        $this->assertNull($uploadQueueManager->dequeue(1), 'Unprocessable groups must not be handed out');
        $this->assertSame(
            [$resolvableSubmission, $unresolvableSubmission],
            $failed,
            'Expected every existing submission in the discarded group to be failed visibly',
        );
        $this->assertNotEmpty(
            array_filter($queries, static fn(string $q) => str_starts_with($q, 'DELETE')),
            'Expected the unprocessable row to be removed from the queue',
        );
    }

    public function testDequeueFailsSubmissionsOnceAttemptsAreExhausted()
    {
        $queries = [];
        $selects = [];
        $failed = [];
        $manager = $this->buildManager(
            [
                ['id' => 7, 'batch_uid' => '', 'submission_ids' => '1', 'claimed' => '2020-01-01 00:00:00', 'attempts' => UploadQueueManager::MAX_ATTEMPTS],
                null,
            ],
            [1 => 1],
            $queries,
            $selects,
            $failed,
        );

        $this->assertNull($manager->dequeue(1), 'Exhausted rows must not be handed out again');
        $this->assertCount(1, $failed, 'Expected the submission to be failed visibly');
        $this->assertStringContainsString('attempt', strtolower($failed[0]));
        $this->assertNotEmpty(
            array_filter($queries, static fn(string $q) => str_starts_with($q, 'DELETE')),
            'Expected the exhausted row to be removed from the queue',
        );
    }

    /**
     * @param array $rows        sequential getRowArray() return values
     * @param int[] $submissions map of submission id => source blog id that exist
     */
    private function buildManager(
        array $rows,
        array $submissions,
        array &$queries,
        array &$selects = [],
        array &$failed = [],
    ): UploadQueueManager {
        $stored = [];
        foreach ($submissions as $id => $sourceBlogId) {
            $submission = $this->createMock(SubmissionEntity::class);
            $submission->method('getId')->willReturn($id);
            $submission->method('getSourceId')->willReturn(1);
            $submission->method('getSourceBlogId')->willReturn($sourceBlogId);
            $stored[] = $submission;
        }

        $this->mockDbAl();
        $db = $this->getMockBuilder(DB::class)
            ->setConstructorArgs([new class {
                public string $base_prefix = '';
                public function getRowArray() {}
                public function query() {}
            }])
            ->onlyMethods(['getRowArray', 'query'])
            ->getMock();

        $index = 0;
        $db->method('getRowArray')->willReturnCallback(function ($query) use ($rows, &$index, &$selects) {
            $selects[] = $query;
            return $rows[$index++] ?? null;
        });
        $db->method('query')->willReturnCallback(function ($query) use (&$queries) {
            $queries[] = $query;
            return true;
        });

        $submissionManager = $this->createMock(SubmissionManager::class);
        $submissionManager->method('getEntityById')->willReturnCallback(function ($id) use ($stored) {
            foreach ($stored as $submission) {
                if ($submission->getId() === $id) {
                    return $submission;
                }
            }
            return null;
        });
        $submissionManager->method('setErrorMessage')->willReturnCallback(
            function (SubmissionEntity $submission, string $message) use (&$failed) {
                $failed[] = $message;
                return $submission;
            }
        );

        $settingsManager = $this->createMock(SettingsManager::class);
        $settingsManager->method('getSmartlingLocaleBySubmission')->willReturn('de-DE');

        return new UploadQueueManager(
            $this->createMock(ApiWrapperInterface::class),
            $settingsManager,
            $db,
            $submissionManager,
        );
    }
}
