<?php

namespace Smartling\Tests\Smartling\Jobs;

use PHPUnit\Framework\TestCase;
use Smartling\ApiWrapperInterface;
use Smartling\Helpers\Cache;
use Smartling\Helpers\FileUriHelper;
use Smartling\Jobs\SubmissionCollectorJob;
use Smartling\Queue\QueueInterface;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionManager;
use Smartling\Tests\Mocks\WordpressFunctionsMockHelper;

class SubmissionCollectorJobTest extends TestCase
{
    public function setUp(): void
    {
        WordpressFunctionsMockHelper::injectFunctionsMocks();
    }

    public function testInstantTranslationSubmissionsAreNotQueued(): void
    {
        $submissionManager = $this->createMock(SubmissionManager::class);
        $submissionManager->method('find')->willReturn([]);
        $submissionManager->method('getGroupedIdsByFileUri')->willReturn([
            ['fileUri' => '/page_post_1_5.xml', 'ids' => '1,2'],
            ['fileUri' => 'fileuid123:mtuid456', 'ids' => '3'],
        ]);

        $queue = $this->createMock(QueueInterface::class);
        $queue->expects($this->once())->method('enqueue')
            ->with(['/page_post_1_5.xml' => [1, 2]], QueueInterface::QUEUE_NAME_LAST_MODIFIED_CHECK_QUEUE);

        (new SubmissionCollectorJob(
            $this->createMock(ApiWrapperInterface::class),
            $this->createMock(Cache::class),
            $this->createMock(FileUriHelper::class),
            $this->createMock(SettingsManager::class),
            $submissionManager,
            0,
            '',
            $queue,
        ))->run('');
    }
}
