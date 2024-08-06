<?php

namespace Smartling\Jobs;

use PHPUnit\Framework\TestCase;
use Smartling\ApiWrapperInterface;
use Smartling\DbAl\UploadQueueManager;
use Smartling\Helpers\Cache;
use Smartling\Helpers\FileUriHelper;
use Smartling\Models\IntStringPairCollection;
use Smartling\Models\UploadQueueItem;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

class UploadJobTest extends TestCase {
    public function testQueueItemsWithoutBatchUidAndExistingJob()
    {
        $newBatchUid = 'newBatchUid';
        $previousJobUid = 'previousJobUid';
        $profile = $this->createMock(ConfigurationProfileEntity::class);

        $api = $this->createMock(ApiWrapperInterface::class);
        $api->expects($this->never())->method('getOrCreateJobInfoForDailyBucketJob');
        $api->expects($this->once())->method('createBatch')->with($profile, $previousJobUid)->willReturn($newBatchUid);

        $jobManager = $this->createStub(JobManager::class);
        $jobManager->method('getBySubmissionId')->willReturn(new JobEntity('', $previousJobUid, ''));

        $settingsManager = $this->createMock(SettingsManager::class);
        $settingsManager->method('getActiveProfiles')->willReturn([$profile]);
        $settingsManager->method('getSingleSettingsProfile')->willReturn($profile);

        $submission = $this->createMock(SubmissionEntity::class);
        $submission->method('getId')->willReturn(1);

        $uploadQueueItem = $this->createMock(UploadQueueItem::class);
        $uploadQueueItem->method('getSubmissions')->willReturn([$submission]);
        $uploadQueueItem->method('getBatchUid')->willReturn('');
        $uploadQueueItem->method('getSmartlingLocales')->willReturn(new IntStringPairCollection([2 => '']));
        $uploadQueueItem->expects($this->once())->method('setBatchUid')->with($newBatchUid);

        $uploadQueueManager = $this->createMock(UploadQueueManager::class);
        $uploadQueueManager->expects($this->exactly(2))->method('dequeue')->willReturn(
            $uploadQueueItem,
            null,
        );

        (new UploadJob(
            $api,
            $this->createMock(Cache::class),
            $this->createMock(FileUriHelper::class),
            $jobManager,
            $settingsManager,
            $this->createMock(SubmissionManager::class),
            $uploadQueueManager,
            0,
            "",
            0
        ))->run();
    }
}
