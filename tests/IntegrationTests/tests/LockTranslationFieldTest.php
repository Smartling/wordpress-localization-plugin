<?php

namespace Smartling\Tests\IntegrationTests\tests;

use Smartling\Submissions\SubmissionEntity;
use Smartling\Tests\IntegrationTests\SmartlingUnitTestCaseAbstract;

class LockTranslationFieldTest extends SmartlingUnitTestCaseAbstract
{
    /**
     * Test lock post flow.
     * Create post. Translate it. Edit and translate without locking. Edit
     * and translate with locking.
     */
    public function testLockPostFlow()
    {
        // Create post and submission.
        $postId = $this->createPost('post', 'Locked post title');
        $submission = $this->createSubmission('post', $postId);
        $submission->getFileUri();
        $submission = $this->getSubmissionManager()->storeEntity($submission);
        $sourceTitle = $this->getContentHelper()->readSourceContent($submission)->getTitle();

        /**
         * @var SubmissionEntity $submission
         */
        $submission = $this->uploadDownload($submission);

        $translatedTitle = $this->getContentHelper()->readTargetContent($submission)->getTitle();

        $this->editPost(
            [
                'ID'         => $postId,
                'post_title' => $sourceTitle . ' EDITED.',
            ]
        );

        $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_NEW);
        $submission = $this->getSubmissionManager()->storeEntity($submission);
        $submission = $this->uploadDownload($submission);

        $translatedTitleEdited = $this->getContentHelper()->readTargetContent($submission)->getTitle();
        $this->assertNotEquals($translatedTitle, $translatedTitleEdited);

        $this->editPost(
            [
                'ID'         => $postId,
                'post_title' => $sourceTitle . ' EDITED ONCE AGAIN.',
            ]
        );

        $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_NEW);
        $submission->setLockedFields(serialize(['entity/post_title']));
        $submission = $this->getSubmissionManager()->storeEntity($submission);

        $submission = $this->uploadDownload($submission);

        $translatedTitleEditedOnceAgain = $this->getContentHelper()->readTargetContent($submission)->getTitle();
        $this->assertEquals($translatedTitleEdited, $translatedTitleEditedOnceAgain);
    }

}

