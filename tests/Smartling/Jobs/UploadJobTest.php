<?php

namespace Smartling\Tests\Smartling\Jobs;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Smartling\ApiWrapperInterface;
use Smartling\Base\ExportedAPI;
use Smartling\DbAl\UploadQueueManager;
use Smartling\Exception\SmartlingDbException;
use Smartling\Helpers\Cache;
use Smartling\Helpers\FileUriHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Jobs\UploadJob;
use Smartling\Models\IntStringPair;
use Smartling\Models\IntStringPairCollection;
use Smartling\Models\UploadQueueItem;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;
use Smartling\Tests\Mocks\WordpressFunctionsMockHelper;

class UploadJobTest extends TestCase
{
    public function setUp(): void
    {
        WordpressFunctionsMockHelper::injectFunctionsMocks();
    }

    public function testSuccessfulUploadRemovesItemFromQueue()
    {
        $item = $this->buildItem();
        $uploadQueueManager = $this->buildQueueManager($item);
        $uploadQueueManager->expects($this->once())->method('complete')->with($item);

        $this->buildJob($uploadQueueManager)->run('');
    }

    /**
     * A queue row must only be removed once the upload has been accounted for, so
     * that a process death mid-upload leaves the work recoverable.
     */
    public function testFailedUploadStillRemovesItemButRecordsError()
    {
        $submission = $this->createMock(SubmissionEntity::class);
        $submission->method('getId')->willReturn(1);
        $submission->method('getFileUri')->willReturn('file.xml');
        $submission->method('getSourceBlogId')->willReturn(1);
        $item = $this->buildItem($submission);

        $uploadQueueManager = $this->buildQueueManager($item);
        $uploadQueueManager->expects($this->once())->method('complete')->with($item);

        $submissionManager = $this->createMock(SubmissionManager::class);
        $submissionManager->expects($this->once())->method('setErrorMessage')
            ->with($submission, $this->stringContains('boom'));

        $this->buildJob($uploadQueueManager, $submissionManager, static function () {
            throw new \RuntimeException('boom');
        })->run('');
    }

    /**
     * error_get_last()-style crashes surface as \Error, not \Exception. The dispatch
     * must be resilient to those too, or a single bad hook aborts the whole cron run
     * without ever completing the claimed queue item.
     */
    public function testUploadThrowableFromHookStillRemovesItemButRecordsError()
    {
        $submission = $this->createMock(SubmissionEntity::class);
        $submission->method('getId')->willReturn(1);
        $submission->method('getFileUri')->willReturn('file.xml');
        $submission->method('getSourceBlogId')->willReturn(1);
        $item = $this->buildItem($submission);

        $uploadQueueManager = $this->buildQueueManager($item);
        $uploadQueueManager->expects($this->once())->method('complete')->with($item);

        $submissionManager = $this->createMock(SubmissionManager::class);
        $submissionManager->expects($this->once())->method('setErrorMessage')
            ->with($submission, $this->stringContains('boom'));

        $this->buildJob($uploadQueueManager, $submissionManager, static function () {
            throw new \Error('boom');
        })->run('');
    }

    /**
     * A row is claimed (and its attempt counter incremented) by dequeue() before this
     * check runs. If the row isn't completed here, it stays claimed until the stale
     * claim window passes and is retried needlessly, eventually failing with a
     * misleading "terminated unexpectedly" message instead of the real cause.
     */
    public function testSkipsUploadAndCompletesQueueItemWhenNoActiveProfileFound()
    {
        $item = $this->buildItem();
        $uploadQueueManager = $this->buildQueueManager($item);
        $uploadQueueManager->expects($this->once())->method('complete')->with($item);

        $this->buildJob($uploadQueueManager, null, null, static function () {
            throw new SmartlingDbException('no profile');
        })->run('');
    }

    /**
     * A cloned submission's content was already uploaded at clone time; re-uploading
     * it would be a duplicate. Before this fix, isCloned() only logged that the item
     * was "being skipped" without actually skipping it.
     */
    public function testClonedSubmissionIsSkippedAndCompletesQueueItemWithoutUploading()
    {
        $submission = $this->createMock(SubmissionEntity::class);
        $submission->method('getId')->willReturn(1);
        $submission->method('isCloned')->willReturn(true);
        $item = $this->buildItem($submission);

        $uploadQueueManager = $this->buildQueueManager($item);
        $uploadQueueManager->expects($this->once())->method('complete')->with($item);

        $uploaded = false;
        $this->buildJob($uploadQueueManager, null, static function () use (&$uploaded) {
            $uploaded = true;
        })->run('');

        $this->assertFalse($uploaded, 'Cloned submissions must not be uploaded');
    }

    /**
     * A queue item groups submissions for the same content across multiple target
     * locales; only the first one is used to look up the profile/batch job. If either
     * lookup fails, every submission in the group must be failed visibly, not just the
     * first, or siblings silently vanish with the row while staying in "New" forever.
     */
    public function testFailsEverySubmissionWhenNoActiveProfileFound()
    {
        [$item, $submission1, $submission2] = $this->buildTwoSubmissionItem();
        $uploadQueueManager = $this->buildQueueManager($item);
        $uploadQueueManager->expects($this->once())->method('complete')->with($item);

        $submissionManager = $this->createMock(SubmissionManager::class);
        $failed = [];
        $submissionManager->method('setErrorMessage')->willReturnCallback(
            static function (SubmissionEntity $submission, string $message) use (&$failed) {
                $failed[] = $submission;
                return $submission;
            },
        );

        $this->buildJob($uploadQueueManager, $submissionManager, null, static function () {
            throw new SmartlingDbException('no profile');
        })->run('');

        $this->assertSame([$submission1, $submission2], $failed, 'Expected every submission in the group to be failed visibly');
    }

    /**
     * Same as above, but for the daily-bucket-job lookup failing instead of the profile
     * lookup.
     */
    public function testFailsEverySubmissionWhenDailyBucketJobCannotBeCreated()
    {
        [$item, $submission1, $submission2] = $this->buildTwoSubmissionItem();
        $uploadQueueManager = $this->buildQueueManager($item);
        $uploadQueueManager->expects($this->once())->method('complete')->with($item);

        $submissionManager = $this->createMock(SubmissionManager::class);
        $failed = [];
        $submissionManager->method('setErrorMessage')->willReturnCallback(
            static function (SubmissionEntity $submission, string $message) use (&$failed) {
                $failed[] = $submission;
                return $submission;
            },
        );

        $api = $this->createMock(ApiWrapperInterface::class);
        $api->method('getOrCreateJobInfoForDailyBucketJob')->willThrowException(new \RuntimeException('boom'));

        $this->buildJob($uploadQueueManager, $submissionManager, null, null, $api)->run('');

        $this->assertSame([$submission1, $submission2], $failed, 'Expected every submission in the group to be failed visibly');
    }

    /**
     * @return array{0: UploadQueueItem, 1: SubmissionEntity, 2: SubmissionEntity}
     */
    private function buildTwoSubmissionItem(): array
    {
        $submission1 = $this->createMock(SubmissionEntity::class);
        $submission1->method('getId')->willReturn(1);
        $submission1->method('getFileUri')->willReturn('file.xml');
        $submission1->method('getSourceBlogId')->willReturn(1);
        $submission2 = $this->createMock(SubmissionEntity::class);
        $submission2->method('getId')->willReturn(2);
        $submission2->method('getFileUri')->willReturn('file.xml');
        $submission2->method('getSourceBlogId')->willReturn(1);

        $item = new UploadQueueItem(
            [$submission1, $submission2],
            '',
            new IntStringPairCollection([new IntStringPair(1, 'de-DE'), new IntStringPair(2, 'fr-FR')]),
            42,
        );

        return [$item, $submission1, $submission2];
    }

    /**
     * processUploadQueue() dispatches through the WordPress function proxy so the hook
     * call can be mocked in tests; processCloning() must do the same, or bugs in the
     * cloning dispatch have no unit-test coverage.
     */
    public function testCloningDispatchesThroughWordpressProxy()
    {
        $uploadQueueManager = $this->createMock(UploadQueueManager::class);
        $uploadQueueManager->method('length')->willReturn(0);
        $uploadQueueManager->method('dequeue')->willReturn(null);

        $submission = $this->createMock(SubmissionEntity::class);
        $submissionManager = $this->createMock(SubmissionManager::class);
        $calls = 0;
        $submissionManager->method('findSubmissionForCloning')->willReturnCallback(
            function () use ($submission, &$calls) {
                return $calls++ === 0 ? $submission : null;
            },
        );

        $settingsManager = $this->createMock(SettingsManager::class);
        $settingsManager->method('getActiveProfile')
            ->willReturn($this->createMock(ConfigurationProfileEntity::class));

        $wpProxy = $this->createMock(WordpressFunctionProxyHelper::class);
        $wpProxy->method('get_current_blog_id')->willReturn(1);
        $wpProxy->expects($this->once())->method('do_action')
            ->with(ExportedAPI::ACTION_SMARTLING_CLONE_CONTENT, $submission);

        (new UploadJob(
            $this->createMock(ApiWrapperInterface::class),
            $this->createMock(Cache::class),
            $this->createMock(FileUriHelper::class),
            $settingsManager,
            $submissionManager,
            $uploadQueueManager,
            $wpProxy,
            0,
            'hourly',
        ))->run('');
    }

    private function buildItem(?SubmissionEntity $submission = null): UploadQueueItem
    {
        if ($submission === null) {
            $submission = $this->createMock(SubmissionEntity::class);
            $submission->method('getId')->willReturn(1);
            $submission->method('getFileUri')->willReturn('file.xml');
            $submission->method('getSourceBlogId')->willReturn(1);
        }

        return new UploadQueueItem(
            [$submission],
            'batchUid',
            new IntStringPairCollection([new IntStringPair(1, 'de-DE')]),
            42,
        );
    }

    private function buildQueueManager(UploadQueueItem $item): UploadQueueManager|MockObject
    {
        $uploadQueueManager = $this->createMock(UploadQueueManager::class);
        $uploadQueueManager->method('length')->willReturn(1);
        $calls = 0;
        $uploadQueueManager->method('dequeue')->willReturnCallback(function () use ($item, &$calls) {
            return $calls++ === 0 ? $item : null;
        });

        return $uploadQueueManager;
    }

    private function buildJob(
        UploadQueueManager $uploadQueueManager,
        ?SubmissionManager $submissionManager = null,
        ?callable $onSendForTranslation = null,
        ?callable $onGetSingleSettingsProfile = null,
        ?ApiWrapperInterface $api = null,
    ): UploadJob {
        $settingsManager = $this->createMock(SettingsManager::class);
        if ($onGetSingleSettingsProfile !== null) {
            $settingsManager->method('getSingleSettingsProfile')->willReturnCallback($onGetSingleSettingsProfile);
        } else {
            $settingsManager->method('getSingleSettingsProfile')
                ->willReturn($this->createMock(ConfigurationProfileEntity::class));
        }
        $settingsManager->method('getActiveProfile')
            ->willReturn($this->createMock(ConfigurationProfileEntity::class));

        $submissionManager ??= $this->createMock(SubmissionManager::class);
        $submissionManager->method('findSubmissionForCloning')->willReturn(null);

        $wpProxy = $this->createMock(WordpressFunctionProxyHelper::class);
        $wpProxy->method('get_current_blog_id')->willReturn(1);
        if ($onSendForTranslation !== null) {
            $wpProxy->method('do_action')->willReturnCallback($onSendForTranslation);
        }

        return new UploadJob(
            $api ?? $this->createMock(ApiWrapperInterface::class),
            $this->createMock(Cache::class),
            $this->createMock(FileUriHelper::class),
            $settingsManager,
            $submissionManager,
            $uploadQueueManager,
            $wpProxy,
            0,
            'hourly',
        );
    }
}
