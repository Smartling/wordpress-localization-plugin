<?php

namespace Smartling\Tests\FTS;

use PHPUnit\Framework\TestCase;
use Smartling\ApiWrapperInterface;
use Smartling\Base\SmartlingCore;
use Smartling\FTS\FtsApiWrapper;
use Smartling\FTS\FtsService;
use Smartling\Helpers\ContentHelper;
use Smartling\Helpers\PostContentHelper;
use Smartling\Helpers\XmlHelper;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

/**
 * Unit tests for FtsService
 */
class FtsServiceTest extends TestCase
{
    private FtsService $ftsService;
    private FtsApiWrapper $ftsApiWrapper;
    private ApiWrapperInterface $apiWrapper;
    private SubmissionManager $submissionManager;
    private ContentHelper $contentHelper;
    private SmartlingCore $core;
    private SettingsManager $settingsManager;
    private XmlHelper $xmlHelper;
    private PostContentHelper $postContentHelper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ftsApiWrapper = $this->createMock(FtsApiWrapper::class);
        $this->apiWrapper = $this->createMock(ApiWrapperInterface::class);
        $this->submissionManager = $this->createMock(SubmissionManager::class);
        $this->contentHelper = $this->createMock(ContentHelper::class);
        $this->core = $this->createMock(SmartlingCore::class);
        $this->settingsManager = $this->createMock(SettingsManager::class);
        $this->xmlHelper = $this->createMock(XmlHelper::class);
        $this->postContentHelper = $this->createMock(PostContentHelper::class);

        $this->ftsService = new FtsService(
            $this->ftsApiWrapper,
            $this->apiWrapper,
            $this->submissionManager,
            $this->contentHelper,
            $this->core,
            $this->settingsManager,
            $this->xmlHelper,
            $this->postContentHelper
        );
    }

    public function testConstructor(): void
    {
        $this->assertInstanceOf(FtsService::class, $this->ftsService);
    }

    public function testGetNextPollInterval(): void
    {
        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->ftsService);
        $method = $reflection->getMethod('getNextPollInterval');
        $method->setAccessible(true);

        // Test exponential backoff intervals
        $this->assertEquals(1000, $method->invoke($this->ftsService, 0));
        $this->assertEquals(2000, $method->invoke($this->ftsService, 1));
        $this->assertEquals(4000, $method->invoke($this->ftsService, 2));
        $this->assertEquals(8000, $method->invoke($this->ftsService, 3));
        $this->assertEquals(16000, $method->invoke($this->ftsService, 4));

        // Test max backoff interval (30s) for subsequent polls
        $this->assertEquals(30000, $method->invoke($this->ftsService, 5));
        $this->assertEquals(30000, $method->invoke($this->ftsService, 6));
        $this->assertEquals(30000, $method->invoke($this->ftsService, 10));
    }

    public function testPollIntervalsConstants(): void
    {
        // Verify the polling configuration constants
        $reflection = new \ReflectionClass($this->ftsService);

        $pollIntervals = $reflection->getConstant('POLL_INTERVALS');
        $this->assertIsArray($pollIntervals);
        $this->assertEquals([1000, 2000, 4000, 8000, 16000], $pollIntervals);

        $maxBackoff = $reflection->getConstant('MAX_BACKOFF_INTERVAL_MS');
        $this->assertEquals(30000, $maxBackoff);

        $timeout = $reflection->getConstant('TIMEOUT_MS');
        $this->assertEquals(120000, $timeout);
    }

    public function testFtsStatusConstants(): void
    {
        // Verify FTS status state constants exist
        $reflection = new \ReflectionClass($this->ftsService);

        $this->assertEquals('QUEUED', $reflection->getConstant('STATE_QUEUED'));
        $this->assertEquals('PROCESSING', $reflection->getConstant('STATE_PROCESSING'));
        $this->assertEquals('COMPLETED', $reflection->getConstant('STATE_COMPLETED'));
        $this->assertEquals('FAILED', $reflection->getConstant('STATE_FAILED'));
        $this->assertEquals('CANCELLED', $reflection->getConstant('STATE_CANCELLED'));
    }

    public function testRequestInstantTranslationHandlesException(): void
    {
        $submission = $this->createMock(SubmissionEntity::class);
        $submission->method('getId')->willReturn(123);
        $submission->method('getContentType')->willReturn('post');
        $submission->method('getSourceBlogId')->willReturn(1);
        $submission->method('getTargetBlogId')->willReturn(2);

        // Mock SmartlingCore to throw exception during prepareUpload
        $this->core
            ->method('prepareUpload')
            ->willThrowException(new \Exception('Test exception'));

        $result = $this->ftsService->requestInstantTranslation($submission);

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertEquals('error', $result['status']);
        $this->assertStringContainsString('Test exception', $result['message']);
    }
}
