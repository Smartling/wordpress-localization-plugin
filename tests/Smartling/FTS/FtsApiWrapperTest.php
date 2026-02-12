<?php

namespace Smartling\Tests\FTS;

use PHPUnit\Framework\TestCase;
use Smartling\FTS\FtsApiWrapper;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;

/**
 * Unit tests for FtsApiWrapper
 */
class FtsApiWrapperTest extends TestCase
{
    private FtsApiWrapper $ftsApiWrapper;
    private SettingsManager $settingsManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settingsManager = $this->createMock(SettingsManager::class);

        $this->ftsApiWrapper = new FtsApiWrapper(
            $this->settingsManager,
            'test-plugin',
            '1.0.0'
        );
    }

    public function testConstructor(): void
    {
        $this->assertInstanceOf(FtsApiWrapper::class, $this->ftsApiWrapper);
    }

    public function testUploadFileRequiresConfiguration(): void
    {
        $this->expectException(\Smartling\Exception\SmartlingDbException::class);

        $submission = $this->createMock(SubmissionEntity::class);
        $submission->method('getSourceBlogId')->willReturn(1);

        $this->settingsManager
            ->method('getSingleSettingsProfile')
            ->willThrowException(new \Smartling\Exception\SmartlingDbException('No profile found'));

        $this->ftsApiWrapper->uploadFile(
            $submission,
            '/tmp/test.xml',
            'test.xml',
            'xml'
        );
    }

    public function testSubmitForInstantTranslationRequiresConfiguration(): void
    {
        $this->expectException(\Smartling\Exception\SmartlingDbException::class);

        $submission = $this->createMock(SubmissionEntity::class);
        $submission->method('getSourceBlogId')->willReturn(1);

        $this->settingsManager
            ->method('getSingleSettingsProfile')
            ->willThrowException(new \Smartling\Exception\SmartlingDbException('No profile found'));

        $this->ftsApiWrapper->submitForInstantTranslation(
            $submission,
            'file-uid-123',
            'en',
            ['es-ES']
        );
    }

    public function testPollTranslationStatusRequiresConfiguration(): void
    {
        $this->expectException(\Smartling\Exception\SmartlingDbException::class);

        $submission = $this->createMock(SubmissionEntity::class);
        $submission->method('getSourceBlogId')->willReturn(1);

        $this->settingsManager
            ->method('getSingleSettingsProfile')
            ->willThrowException(new \Smartling\Exception\SmartlingDbException('No profile found'));

        $this->ftsApiWrapper->pollTranslationStatus(
            $submission,
            'file-uid-123',
            'mt-uid-456'
        );
    }

    public function testDownloadTranslatedFileRequiresConfiguration(): void
    {
        $this->expectException(\Smartling\Exception\SmartlingDbException::class);

        $submission = $this->createMock(SubmissionEntity::class);
        $submission->method('getSourceBlogId')->willReturn(1);

        $this->settingsManager
            ->method('getSingleSettingsProfile')
            ->willThrowException(new \Smartling\Exception\SmartlingDbException('No profile found'));

        $this->ftsApiWrapper->downloadTranslatedFile(
            $submission,
            'file-uid-123',
            'mt-uid-456',
            'es-ES'
        );
    }
}
