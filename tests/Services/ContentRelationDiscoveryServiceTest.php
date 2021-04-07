<?php

namespace Smartling\Tests\Services;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Smartling\ApiWrapper;
use Smartling\Helpers\ContentHelper;
use Smartling\Helpers\SiteHelper;
use Smartling\Services\ContentRelationsDiscoveryService;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;
use Smartling\Tests\Mocks\WordpressFunctionsMockHelper;

class ContentRelationDiscoveryServiceTest extends TestCase
{
    protected function setUp(): void
    {
        WordpressFunctionsMockHelper::injectFunctionsMocks();
    }

    public function testCreateSubmissionsHandler()
    {
        $sourceBlogId = 1;
        $sourceId = 48;
        $contentType = 'post';
        $targetBlogId = 2;
        $batchUid = 'batchUid';

        $apiWrapper = $this->getMockBuilder(ApiWrapper::class)->disableOriginalConstructor()->getMock();
        $apiWrapper->method('retrieveBatch')->willReturn($batchUid);

        $settingsManager = $this->getMockBuilder(SettingsManager::class)->disableOriginalConstructor()->getMock();
        $settingsManager->method('getSingleSettingsProfile')->willReturn(new ConfigurationProfileEntity());

        $siteHelper = $this->createMock(SiteHelper::class);
        $siteHelper->method('getCurrentBlogId')->willReturn($sourceBlogId);

        $contentHelper = new ContentHelper();
        $contentHelper->setSiteHelper($siteHelper);

        $submission = $this->createPartialMock(SubmissionEntity::class, ['getFileUri', 'setBatchUid', 'setStatus']);
        $submission->expects(self::once())->method('setBatchUid')->with($batchUid);
        $submission->expects(self::once())->method('setStatus')->with(SubmissionEntity::SUBMISSION_STATUS_NEW);

        $submissionManager = $this->getMockBuilder(SubmissionManager::class)->disableOriginalConstructor()->getMock();

        $submissionManager->expects(self::once())->method('find')->with([
            'source_blog_id' => $sourceBlogId,
            'target_blog_id' => $targetBlogId,
            'content_type' => $contentType,
            'source_id' => $sourceId,
        ])->willReturn([$submission]);

        $submissionManager->expects(self::once())->method('storeEntity')->with($submission);

        $x = $this->getContentRelationDiscoveryService($apiWrapper, $contentHelper, $settingsManager, $submissionManager);

        $x->expects(self::once())->method('returnResponse')->with(['status' => 'SUCCESS']);

        $x->createSubmissionsHandler([
            'source' => ['contentType' => $contentType, 'id' => [$sourceId]],
            'job' =>
                [
                    'id' => 'abcdef123456',
                    'name' => '',
                    'description' => '',
                    'dueDate' => '',
                    'timeZone' => 'Europe/Kiev',
                    'authorize' => 'true',
                ],
            'targetBlogIds' => $targetBlogId,
            'relations' => [],
        ]);
    }

    public function testBulkSubmitHandler() {
        $sourceBlogId = 1;
        $sourceIds = [48, 49];
        $contentType = 'post';
        $targetBlogId = 2;
        $batchUid = 'batchUid';

        $apiWrapper = $this->getMockBuilder(ApiWrapper::class)->disableOriginalConstructor()->getMock();
        $apiWrapper->method('retrieveBatch')->willReturn($batchUid);

        $settingsManager = $this->getMockBuilder(SettingsManager::class)->disableOriginalConstructor()->getMock();
        $settingsManager->method('getSingleSettingsProfile')->willReturn(new ConfigurationProfileEntity());

        $siteHelper = $this->createMock(SiteHelper::class);
        $siteHelper->method('getCurrentBlogId')->willReturn($sourceBlogId);

        $contentHelper = new ContentHelper();
        $contentHelper->setSiteHelper($siteHelper);

        $submission = $this->createPartialMock(SubmissionEntity::class, ['getFileUri', 'setBatchUid', 'setStatus']);
        // $submission->expects(self::exactly(count($sourceIds)))->method('setBatchUid')->with($batchUid);
        $submission->expects(self::exactly(count($sourceIds)))->method('setStatus')->with(SubmissionEntity::SUBMISSION_STATUS_NEW);

        $submissionManager = $this->getMockBuilder(SubmissionManager::class)->disableOriginalConstructor()->getMock();

        $submissionManager->expects(self::exactly(count($sourceIds)))->method('find')->withConsecutive([[
            'source_blog_id' => $sourceBlogId,
            'target_blog_id' => $targetBlogId,
            'content_type' => $contentType,
            'source_id' => $sourceIds[0],
        ]], [[
            'source_blog_id' => $sourceBlogId,
            'target_blog_id' => $targetBlogId,
            'content_type' => $contentType,
            'source_id' => $sourceIds[1],
        ]])->willReturn([$submission]);

        $submissionManager->expects(self::never())->method('getSubmissionEntity')->with([$contentType, $sourceBlogId]);

        $submissionManager->expects(self::exactly(count($sourceIds)))->method('storeEntity')->with($submission);

        $x = $this->getContentRelationDiscoveryService($apiWrapper, $contentHelper, $settingsManager, $submissionManager);

        $x->expects(self::once())->method('returnResponse')->with(['status' => 'SUCCESS']);

        $x->createSubmissionsHandler([
            'source' => ['contentType' => $contentType, 'id' => [0]],
            'job' =>
                [
                    'id' => 'abcdef123456',
                    'name' => '',
                    'description' => '',
                    'dueDate' => '',
                    'timeZone' => 'Europe/Kiev',
                    'authorize' => 'true',
                ],
            'targetBlogIds' => $targetBlogId,
            'ids' => $sourceIds,
        ]);
    }

    /**
     * @param ApiWrapper $apiWrapper
     * @param ContentHelper $contentHelper
     * @param SettingsManager $settingsManager
     * @param SubmissionManager $submissionManager
     * @return MockObject|ContentRelationsDiscoveryService
     */
    private function getContentRelationDiscoveryService(
        ApiWrapper $apiWrapper,
        ContentHelper $contentHelper,
        SettingsManager $settingsManager,
        SubmissionManager $submissionManager
    ) {
        $x = $this->createPartialMock(ContentRelationsDiscoveryService::class, [
            'getApiWrapper',
            'getContentHelper',
            'getSettingsManager',
            'getSubmissionManager',
            'returnResponse',
        ]);
        $x->method('getApiWrapper')->willReturn($apiWrapper);
        $x->method('getContentHelper')->willReturn($contentHelper);
        $x->method('getSettingsManager')->willReturn($settingsManager);
        $x->method('getSubmissionManager')->willReturn($submissionManager);

        return $x;
    }
}