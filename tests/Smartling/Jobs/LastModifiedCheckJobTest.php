<?php

namespace Smartling\Tests\Smartling\Jobs;

use PHPUnit\Framework\TestCase;
use Smartling\ApiWrapperInterface;
use Smartling\Helpers\Cache;
use Smartling\Jobs\LastModifiedCheckJob;
use Smartling\Queue\QueueInterface;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;
use Smartling\Tests\Mocks\WordpressFunctionsMockHelper;

class LastModifiedCheckJobTest extends TestCase
{
    public function setUp(): void
    {
        WordpressFunctionsMockHelper::injectFunctionsMocks();
    }

    /**
     * @dataProvider queueDataProvider
     */
    public function testInstantTranslationSubmissionsAreNotChecked(string $queueName): void
    {
        $submission = $this->createMock(SubmissionEntity::class);
        $submission->method('isInstantTranslation')->willReturn(true);
        $submission->expects($this->never())->method('setStatus');

        $submissionManager = $this->createMock(SubmissionManager::class);
        $submissionManager->method('findByIds')->willReturn([$submission]);
        $submissionManager->expects($this->never())->method('setErrorMessage');

        $api = $this->createMock(ApiWrapperInterface::class);
        $api->expects($this->never())->method('lastModified');
        $api->expects($this->never())->method('getStatusForAllLocales');

        $queue = $this->createMock(QueueInterface::class);
        $pending = [
            QueueInterface::QUEUE_NAME_LAST_MODIFIED_CHECK_QUEUE => [],
            QueueInterface::QUEUE_NAME_LAST_MODIFIED_CHECK_AND_FAIL_QUEUE => [],
        ];
        $pending[$queueName][] = ['fileuid:mtuid' => [1]];
        $queue->method('dequeue')->willReturnCallback(
            static function (string $name) use (&$pending) {
                return array_shift($pending[$name]) ?? false;
            }
        );

        (new LastModifiedCheckJob(
            $api,
            $this->createMock(Cache::class),
            $this->createMock(SettingsManager::class),
            $submissionManager,
            0,
            '',
            $queue,
        ))->run('');
    }

    public function queueDataProvider(): array
    {
        return [
            'last modified check queue' => [QueueInterface::QUEUE_NAME_LAST_MODIFIED_CHECK_QUEUE],
            'last modified check and fail queue' => [QueueInterface::QUEUE_NAME_LAST_MODIFIED_CHECK_AND_FAIL_QUEUE],
        ];
    }
}
