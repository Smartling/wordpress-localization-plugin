<?php

namespace Smartling\Tests\IntegrationTests\tests;

use Smartling\Helpers\ArrayHelper;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Tests\IntegrationTests\SmartlingUnitTestCaseAbstract;

class LinkedImageCloneTest extends SmartlingUnitTestCaseAbstract
{
    public function testCloneImage()
    {
        $imageId = $this->createAttachment();
        $postId = $this->createPost('page');
        set_post_thumbnail($postId, $imageId);
        $this->assertTrue(has_post_thumbnail($postId));

        $translationHelper = $this->getTranslationHelper();

        $submission = $translationHelper->prepareSubmission('attachment', 1, $imageId, 2, true);
        $imageSubmissionId = $submission->getId();

        /**
         * Check submission status
         */
        $this->assertSame(SubmissionEntity::SUBMISSION_STATUS_NEW, $submission->getStatus());
        $this->assertSame(1, $submission->getIsCloned());

        $this->executeUpload();

        $submissions = $this->getSubmissionManager()->find(
            [
                'content_type' => 'page',
                'is_cloned' => 1,
            ]);
        $this->assertEmpty($submissions);

        $submissions = $this->getSubmissionManager()->find(
            [
                'content_type' => 'attachment',
                'is_cloned' => 1,
            ]);
        $this->assertCount(1, $submissions);
        $submission = ArrayHelper::first($submissions);
        $this->assertSame($imageSubmissionId, $submission->getId());
    }
}
