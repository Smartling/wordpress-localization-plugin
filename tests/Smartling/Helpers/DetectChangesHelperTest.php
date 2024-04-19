<?php

namespace Smartling\Tests\Smartling\Helpers;

use PHPUnit\Framework\TestCase;
use Smartling\DbAl\UploadQueueManager;
use Smartling\Exception\SmartlingDbException;
use Smartling\Helpers\ContentSerializationHelper;
use Smartling\Helpers\DetectChangesHelper;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Settings\SettingsManager;
use Smartling\Settings\TargetLocale;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;
use Smartling\Tests\Traits\DbAlMock;
use Smartling\Tests\Traits\InvokeMethodTrait;
use Smartling\Tests\Traits\SettingsManagerMock;
use Smartling\Tests\Traits\SiteHelperMock;
use Smartling\Tests\Traits\SubmissionManagerMock;

class DetectChangesHelperTest extends TestCase
{
    use InvokeMethodTrait;
    use SettingsManagerMock;
    use SubmissionManagerMock;
    use DbAlMock;
    use SiteHelperMock;

    public function testGetSubmissionsWithExistingProfile()
    {
        $mock = $this->getSettingsManagerMock();

        $profile = new ConfigurationProfileEntity();

        $expectedLocales = [2, 3, 7];

        $profile->setId(5);
        $profile->setTargetLocales([
            TargetLocale::fromArray(['smartlingLocale' => 'en', 'enabled' => 1, 'blogId' => 2]),
            TargetLocale::fromArray(['smartlingLocale' => 'fr', 'enabled' => 1, 'blogId' => 3]),
            TargetLocale::fromArray(['smartlingLocale' => 'cn', 'enabled' => 0, 'blogId' => 4]),
            TargetLocale::fromArray(['smartlingLocale' => 'zh', 'enabled' => 0, 'blogId' => 6]),
            TargetLocale::fromArray(['smartlingLocale' => 'it', 'enabled' => 1, 'blogId' => 7]),
        ]);

        $mock
            ->expects(self::once())
            ->method('getSingleSettingsProfile')
            ->with(2)
            ->willReturn($profile);

        $submissionManagerMock = $this->mockSubmissionManager($this->mockDbAl());

        $submissionManagerMock
            ->expects(self::once())
            ->method('find')
            ->with([
                SubmissionEntity::FIELD_SOURCE_ID => 5,
                SubmissionEntity::FIELD_SOURCE_BLOG_ID => 2,
                SubmissionEntity::FIELD_CONTENT_TYPE => 'page',
                SubmissionEntity::FIELD_TARGET_BLOG_ID => $expectedLocales,
                SubmissionEntity::FIELD_STATUS => [
                    SubmissionEntity::SUBMISSION_STATUS_NEW,
                    SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS,
                    SubmissionEntity::SUBMISSION_STATUS_COMPLETED,
                    SubmissionEntity::SUBMISSION_STATUS_FAILED,
                ]
            ]);

        $this->invokeMethod($this->getHelper($mock, $submissionManagerMock), 'getSubmissions', [2, 5, 'page']);
    }

    /**
     * @covers \Smartling\Helpers\DetectChangesHelper::getSubmissions
     */
    public function testGetSubmissionsWithoutExistingProfile()
    {
        $mock = $this->getSettingsManagerMock();

        $mock
            ->expects(self::once())
            ->method('getSingleSettingsProfile')
            ->with(2)
            ->willReturnCallback(function () {
                throw new SmartlingDbException();
            });

        $helper = $this->getHelper($mock, $this->mockSubmissionManager($this->mockDbAl()));

        self::assertEquals([], $this->invokeMethod($helper, 'getSubmissions', [2, 5, 'page']));
    }

    private function getHelper(
        SettingsManager $settingsManager,
        SubmissionManager $submissionManager,
    ): DetectChangesHelper {
        return new DetectChangesHelper(
            $this->createMock(ContentSerializationHelper::class),
            $this->createMock(UploadQueueManager::class),
            $settingsManager,
            $submissionManager,
        );
    }
}
