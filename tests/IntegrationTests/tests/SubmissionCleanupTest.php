<?php

namespace Smartling\Tests\IntegrationTests\tests;

use Smartling\Submissions\SubmissionEntity;
use Smartling\Tests\IntegrationTests\SmartlingUnitTestCaseAbstract;

class SubmissionCleanupTest extends SmartlingUnitTestCaseAbstract
{
    private int $targetBlogId = 2;

    private function prepareSubmissionAndUpload(): SubmissionEntity
    {
        $postId = $this->createPost();
        $submission = $this->getTranslationHelper()->prepareSubmission('post', 1, $postId, $this->targetBlogId, true);
        $this->assertSame(SubmissionEntity::SUBMISSION_STATUS_NEW, $submission->getStatus());
        $this->assertSame(1, $submission->getIsCloned());
        $this->executeUpload();
        $submissions = $this->getSubmissionManager()->find([
            'content_type' => 'post',
            'is_cloned'    => 1,
        ]);
        $this->assertCount(1, $submissions);

        return reset($submissions);
    }

    private function assertNoSubmissions()
    {
        $this->assertCount(0, $this->getSubmissionManager()->find([
            'content_type' => 'post',
            'is_cloned' => 1,
        ]));
    }

    public function testRemovedOriginalContent()
    {
        $submission = $this->prepareSubmissionAndUpload();
        wp_delete_post($submission->getSourceId(), true);
        $this->assertNoSubmissions();
    }

    public function testRemovedTranslatedContent()
    {
        $submission = $this->prepareSubmissionAndUpload();
        switch_to_blog($this->targetBlogId);
        wp_delete_post($submission->getTargetId(), true);
        $this->assertNoSubmissions();
    }
}
