<?php

namespace IntegrationTests\tests;

use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\SiteHelper;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Tests\IntegrationTests\SmartlingUnitTestCaseAbstract;

class ChangeStatusOnTranslationTest extends SmartlingUnitTestCaseAbstract
{
    private SettingsManager $settingsManager;
    private SiteHelper $siteHelper;
    private int $sourceBlogId = 1;
    private int $targetBlogId = 2;

    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->settingsManager = $this->getSettingsManager();
        $this->siteHelper = $this->getSiteHelper();
    }

    public function testDraftAfterPublish()
    {
        $profile = $this->settingsManager->getSingleSettingsProfile($this->sourceBlogId);
        $profile->setChangeAssetStatusOnCompletedTranslation(ConfigurationProfileEntity::TRANSLATION_PUBLISHING_MODE_PUBLISH);
        $this->settingsManager->storeEntity($profile);

        $this->assertEquals('publish', $this->createPostAndUploadDownload()->post_status);

        $submissions = $this->getSubmissionManager()->find(
            [
                'content_type' => 'post',
                'status' => SubmissionEntity::SUBMISSION_STATUS_COMPLETED,
            ]
        );

        $this->assertCount(1, $submissions);

        $submission = ArrayHelper::first($submissions);
        $profile->setChangeAssetStatusOnCompletedTranslation(ConfigurationProfileEntity::TRANSLATION_PUBLISHING_MODE_DRAFT);
        $this->settingsManager->storeEntity($profile);

        $this->uploadDownload($submission);

        $post = $this->getTargetPost($this->siteHelper, $submission);
        $this->assertEquals('draft', $post->post_status);
    }

    public function testDraft()
    {
        $profile = $this->settingsManager->getSingleSettingsProfile($this->sourceBlogId);
        $profile->setChangeAssetStatusOnCompletedTranslation(ConfigurationProfileEntity::TRANSLATION_PUBLISHING_MODE_DRAFT);
        $this->settingsManager->storeEntity($profile);

        $this->assertEquals('draft', $this->createPostAndUploadDownload()->post_status);
    }

    private function createPostAndUploadDownload(): \WP_Post
    {
        $postId = $this->createPost();
        $translationHelper = $this->getTranslationHelper();
        $submission = $translationHelper->prepareSubmission('post', $this->sourceBlogId, $postId, $this->targetBlogId);

        $submission->getFileUri();
        $submission = $this->getSubmissionManager()->storeEntity($submission);

        $this->uploadDownload($submission);

        $submissions = $this->getSubmissionManager()->find(
            [
                'content_type' => 'post',
                'source_id' => $postId,
                'status' => SubmissionEntity::SUBMISSION_STATUS_COMPLETED,
            ]
        );

        self::assertCount(1, $submissions);

        $submission = ArrayHelper::first($submissions);

        return $this->getTargetPost($this->siteHelper, $submission);
    }
}
