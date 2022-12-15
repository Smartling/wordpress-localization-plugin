<?php

namespace Smartling\Tests\IntegrationTests\tests;

use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\Helpers\SiteHelper;
use Smartling\Helpers\SubmissionCleanupHelper;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Tests\IntegrationTests\SmartlingUnitTestCaseAbstract;

class SubmissionCleanupTest extends SmartlingUnitTestCaseAbstract
{
    private ?SubmissionCleanupHelper $submissionCleanupHelper = null;
    public function setUp(): void
    {
        if ($this->submissionCleanupHelper === null) {
            $this->submissionCleanupHelper = $this->getSubmissionCleanupHelper();
        }
        $this->submissionCleanupHelper->register();
    }

    public function tearDown(): void
    {
        $this->submissionCleanupHelper?->unregister();
    }

    private int $targetBlogId = 2;

    private function prepareSubmissionAndUpload(): SubmissionEntity
    {
        $postId = $this->createPost();
        $submission = $this->getTranslationHelper()->prepareSubmission('post', 1, $postId, $this->targetBlogId, true);
        $this->assertSame(SubmissionEntity::SUBMISSION_STATUS_NEW, $submission->getStatus());
        $this->assertSame(1, $submission->getIsCloned());
        $this->executeUpload();
        $submissions = $this->getSubmissionManager()->find([
            SubmissionEntity::FIELD_ID => $submission->getId(),
            'content_type' => 'post',
            'is_cloned' => 1,
        ]);
        $this->assertCount(1, $submissions);

        return reset($submissions);
    }

    private function assertNoSubmission(SubmissionEntity $submission)
    {
        $this->assertCount(0, $this->getSubmissionManager()->find([
            SubmissionEntity::FIELD_ID => $submission->getId(),
        ]));
    }

    public function testRemovedOriginalContent()
    {
        $submission = $this->prepareSubmissionAndUpload();
        wp_delete_post($submission->getSourceId(), true);
        $this->assertNoSubmission($submission);
    }

    public function testRemovedTranslatedContent()
    {
        $submission = $this->prepareSubmissionAndUpload();
        (new SiteHelper())->withBlog($this->targetBlogId, function() use ($submission) {
            $submission->target_id = $this->createPost();
            $this->getSubmissionManager()->storeEntity($submission);
            wp_delete_post($submission->getTargetId(), true);
        });

        $this->assertNoSubmission($submission);
    }

    private function getSubmissionCleanupHelper(): SubmissionCleanupHelper
    {
        $localizationPluginProxy = $this->createMock(LocalizationPluginProxyInterface::class);
        $localizationPluginProxy->method('unlinkObjects')->willReturn(true);

        return new SubmissionCleanupHelper($localizationPluginProxy, $this->getSiteHelper(), $this->getSubmissionManager());
    }
}
