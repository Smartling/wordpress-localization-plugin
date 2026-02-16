<?php

namespace Smartling\FTS;

use PHPUnit\Framework\TestCase;
use Smartling\ApiWrapperInterface;
use Smartling\Base\SmartlingCore;
use Smartling\Helpers\ContentHelper;
use Smartling\Helpers\PostContentHelper;
use Smartling\Helpers\SiteHelper;
use Smartling\Helpers\XmlHelper;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

class FtsServiceTest extends TestCase
{
    private FtsService $ftsService;
    private SmartlingCore $core;

    protected function setUp(): void
    {
        parent::setUp();

        $this->core = $this->createMock(SmartlingCore::class);
        $this->siteHelper = $this->createMock(SiteHelper::class);
        $apiWrapper = $this->createMock(ApiWrapperInterface::class);
        $ftsApiWrapper = $this->createMock(FtsApiWrapper::class);
        $postContentHelper = $this->createMock(PostContentHelper::class);
        $settingsManager = $this->createMock(SettingsManager::class);
        $submissionManager = $this->createMock(SubmissionManager::class);
        $xmlHelper = $this->createMock(XmlHelper::class);

        $this->ftsService = new FtsService(
            $apiWrapper,
            $ftsApiWrapper,
            $postContentHelper,
            $settingsManager,
            $this->siteHelper,
            $submissionManager,
            $this->core,
            $xmlHelper,
        );
    }

    public function testGetNextPollInterval(): void
    {
        $this->assertEquals(1000, $this->ftsService->getNextPollInterval(null));
        $this->assertEquals(1000, $this->ftsService->getNextPollInterval(0));
        $this->assertEquals(1000, $this->ftsService->getNextPollInterval(100));
        $this->assertEquals(2000, $this->ftsService->getNextPollInterval(1000));
        $this->assertEquals(4000, $this->ftsService->getNextPollInterval(2000));
        $this->assertEquals(8000, $this->ftsService->getNextPollInterval(4000));
        $this->assertEquals(16000, $this->ftsService->getNextPollInterval(8000));
        $this->assertEquals(30000, $this->ftsService->getNextPollInterval(16000));
        $this->assertEquals(30000, $this->ftsService->getNextPollInterval(PHP_INT_MAX));
    }

    public function testRequestInstantTranslationHandlesException(): void
    {
        $submission = $this->createMock(SubmissionEntity::class);
        $submission->method('getId')->willReturn(123);
        $submission->method('getContentType')->willReturn('post');
        $submission->method('getSourceBlogId')->willReturn(1);
        $submission->method('getTargetBlogId')->willReturn(2);

        $this->core
            ->method('prepareUpload')
            ->willThrowException(new \Exception('Test exception'));

        $result = $this->ftsService->requestInstantTranslation($submission);

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertEquals('error', $result['status']);
        $this->assertStringContainsString('Test exception', $result['message']);
    }

    public function testRequestInstantTranslationBatchWithEmptyArray(): void
    {
        $result = $this->ftsService->requestInstantTranslationBatch([]);

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertEquals('No submissions provided', $result['message']);
    }

    public function testRequestInstantTranslationBatchHandlesException(): void
    {
        $submission1 = $this->createMock(SubmissionEntity::class);
        $submission1->method('getId')->willReturn(123);
        $submission1->method('getContentType')->willReturn('post');
        $submission1->method('getSourceBlogId')->willReturn(1);
        $submission1->method('getTargetBlogId')->willReturn(2);
        $submission1->expects($this->once())->method('setStatus')->with(SubmissionEntity::SUBMISSION_STATUS_FAILED);
        $submission1->expects($this->once())->method('setLastError')->with('Test batch exception');

        $submission2 = $this->createMock(SubmissionEntity::class);
        $submission2->method('getId')->willReturn(124);
        $submission2->method('getContentType')->willReturn('post');
        $submission2->method('getSourceBlogId')->willReturn(1);
        $submission2->method('getTargetBlogId')->willReturn(3);
        $submission2->expects($this->once())->method('setStatus')->with(SubmissionEntity::SUBMISSION_STATUS_FAILED);
        $submission2->expects($this->once())->method('setLastError')->with('Test batch exception');

        $this->core
            ->method('prepareUpload')
            ->willThrowException(new \Exception('Test batch exception'));

        $result = $this->ftsService->requestInstantTranslationBatch([$submission1, $submission2]);

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertEquals('error', $result['status']);
        $this->assertStringContainsString('Test batch exception', $result['message']);
    }

    public function testRequestInstantTranslationBatchRejectsMixedSources(): void
    {
        $submission1 = $this->createMock(SubmissionEntity::class);
        $submission1->method('getId')->willReturn(123);
        $submission1->method('getSourceId')->willReturn(100);

        $submission2 = $this->createMock(SubmissionEntity::class);
        $submission2->method('getId')->willReturn(124);
        $submission2->method('getSourceId')->willReturn(200); // Different source

        $result = $this->ftsService->requestInstantTranslationBatch([$submission1, $submission2]);

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertEquals('Same source submissions expected', $result['message']);
    }

}
