<?php

namespace Smartling\Tests\IntegrationTests\tests;

use Smartling\Submissions\SubmissionEntity;
use Smartling\Tests\IntegrationTests\SmartlingUnitTestCaseAbstract;

class LockTranslationIntegration extends SmartlingUnitTestCaseAbstract
{
    /**
     * Test lock post flow.
     *
     * Create post. Translate it. Edit and translate without locking. Edit
     * and translate with locking.
     */
    public function testLockPostFlow()
    {
        $profile = $this->createProfile();
        $this->getSettingsManager()->storeEntity($profile);

        // Create post and submission.
        $postId = $this->createPost('post', 'Locked post title');
        $submission = $this->createSubmission('post', $postId);
        $submission->getFileUri();
        $submission = $this->getSubmissionManager()->storeEntity($submission);
        $sourceTitle = $this->getContentHelper()->readSourceContent($submission)->getTitle();

        // Case 1: "lock translation" is disabled. Download translation, edit
        // post and re-upload/re-download translation - old translated title !=
        // new translated title.
        $submission = $this->uploadDownload($submission);

        $translatedTitle = $this->getContentHelper()->readTargetContent($submission)->getTitle();

        $this->editPost([
            'ID' => $postId,
            'post_title' => $sourceTitle . ' EDITED.',
        ]);

        $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_NEW);
        $submission = $this->getSubmissionManager()->storeEntity($submission);
        $submission = $this->uploadDownload($submission);

        $translatedTitleEdited = $this->getContentHelper()->readTargetContent($submission)->getTitle();
        $this->assertNotEquals($translatedTitle, $translatedTitleEdited);

        // Case 2: "lock translation" id enabled. Edit post, lock translation
        // and re-upload/re-download it again - previous edited title = new
        // "translated" title.
        $this->editPost([
            'ID' => $postId,
            'post_title' => $sourceTitle . ' EDITED ONCE AGAIN.',
        ]);

        $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_NEW);
        $submission->setIsLocked(true);
        $submission = $this->getSubmissionManager()->storeEntity($submission);

        $submission = $this->uploadDownload($submission);

        $translatedTitleEditedOnceAgain = $this->getContentHelper()->readTargetContent($submission)->getTitle();
        $this->assertEquals($translatedTitleEdited, $translatedTitleEditedOnceAgain);
    }

}
