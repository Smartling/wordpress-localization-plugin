<?php

namespace Smartling\DbAl;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Smartling\ApiWrapperInterface;
use Smartling\Models\IntegerIterator;
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

        $matcherGetRowArray = $this->exactly(5);
        $db->expects($matcherGetRowArray)->method('getRowArray')->willReturnCallback(function ($query) use ($matcherGetRowArray) {
            switch ($matcherGetRowArray->getInvocationCount()) {
                case 1: // first dequeue call gets an item
                    $this->assertEquals('SELECT `id`, `batch_uid`, `submission_ids` FROM `smartling_upload_queue` ORDER BY `id` ASC', $query);
                    return ['id' => 1, 'batch_uid' => '', 'submission_ids' => '1,2'];
                case 2: // second dequeue call gets a submission from other blog
                    $this->assertEquals('SELECT `id`, `batch_uid`, `submission_ids` FROM `smartling_upload_queue` ORDER BY `id` ASC', $query);
                    return ['id' => 2, 'batch_uid' => '', 'submission_ids' => '3'];
                case 3: // second dequeue call gets an item
                    $this->assertEquals("SELECT `id`, `batch_uid`, `submission_ids` FROM `smartling_upload_queue` WHERE ( `id` > '2' ) ORDER BY `id` ASC", $query);
                    return ['id' => 4, 'batch_uid' => '', 'submission_ids' => '4'];
                case 4: // third dequeue call gets a submission from other blog
                    $this->assertEquals('SELECT `id`, `batch_uid`, `submission_ids` FROM `smartling_upload_queue` ORDER BY `id` ASC', $query);
                    return ['id' => 2, 'batch_uid' => '', 'submission_ids' => '3'];
                case 5: // third dequeue call gets no items
                    $this->assertEquals("SELECT `id`, `batch_uid`, `submission_ids` FROM `smartling_upload_queue` WHERE ( `id` > '2' ) ORDER BY `id` ASC", $query);
                    return null;
            }
        });
        $db->expects($this->exactly(2))->method('query')->willReturnCallback(function ($query) {
            $this->assertStringStartsWith('DELETE', $query);
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
}
