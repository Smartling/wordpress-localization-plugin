<?php

namespace Smartling\Tests\IntegrationTests\tests;

use Smartling\Submissions\SubmissionEntity;
use Smartling\Tests\IntegrationTests\SmartlingUnitTestCaseAbstract;

class SubmissionCleanupTest extends SmartlingUnitTestCaseAbstract
{

    /**
     * @return SubmissionEntity
     */
    private function makePreparations()
    {
        $postId = $this->createPost();

        $translationHelper = $this->getTranslationHelper();

        /**
         * @var SubmissionEntity $submission
         */
        $submission = $translationHelper->prepareSubmission('post', 1, $postId, 2, true);

        /**
         * Check submission status
         */
        $this->assertTrue(SubmissionEntity::SUBMISSION_STATUS_NEW === $submission->getStatus());
        $this->assertTrue(1 === $submission->getIsCloned());
        $this->executeUpload();
        $submissions = $this->getSubmissionManager()->find(
            [
                'content_type' => 'post',
                'is_cloned'    => 1,
            ]);
        $this->assertTrue(1 === count($submissions));

        return reset($submissions);
    }

    private function checkSubmissionIsRemoved()
    {
        $submissions = $this->getSubmissionManager()->find(
            [
                'content_type' => 'post',
                'is_cloned'    => 1,
            ]);
        $this->assertTrue(0 === count($submissions));
    }

    public function testRemovedOriginalContent()
    {
        $submission = $this->makePreparations();
        $tmpFile = DIR_TESTDATA . '/test.php';
        file_put_contents($tmpFile, vsprintf('<?php wp_delete_post(%s, true);', [$submission->getSourceId()]));
        $this->wpcli_exec('package', 'install', 'wp-cli/profile-command');
        $this->wpcli_exec('profile', 'eval-file', $tmpFile);
        unlink($tmpFile);
        $this->checkSubmissionIsRemoved();
    }

    public function testRemovedTranslatedContent()
    {
        $submission = $this->makePreparations();
        $tmpFile = DIR_TESTDATA . '/test.php';
        file_put_contents($tmpFile, vsprintf('<?php switch_to_blog(%s); wp_delete_post(%s, true);', [$submission->getTargetBlogId(),
                                                                                                     $submission->getTargetId()]));
        $this->wpcli_exec('package', 'install', 'wp-cli/profile-command');
        $this->wpcli_exec('profile', 'eval-file', $tmpFile);
        unlink($tmpFile);
        $this->checkSubmissionIsRemoved();
    }
}
