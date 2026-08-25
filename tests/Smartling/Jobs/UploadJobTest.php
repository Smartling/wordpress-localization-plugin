<?php

namespace Smartling\Tests\Smartling\Jobs;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Smartling\ApiWrapperInterface;
use Smartling\DbAl\UploadQueueManager;
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
    ): UploadJob {
        $settingsManager = $this->createMock(SettingsManager::class);
        $settingsManager->method('getSingleSettingsProfile')
            ->willReturn($this->createMock(ConfigurationProfileEntity::class));
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
            $this->createMock(ApiWrapperInterface::class),
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
