<?php

namespace Smartling\Tests\IntegrationTests\tests;

use Smartling\Helpers\SimpleStorageHelper;
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

    private function prepareTempFile(SubmissionEntity $submission)
    {
        file_put_contents(
            DIR_TESTDATA . '/test.php',
            vsprintf(
                '<?php wp_delete_post(%s, true);',
                [
                    $submission->getSourceId(),
                ]
            )
        );

        SimpleStorageHelper::set('execFile', DIR_TESTDATA . '/test.php');
    }

    public function testRemovedOriginalContent()
    {
        $submission = $this->makePreparations();
        $this->prepareTempFile($submission);

        $this->wpcli_exec('cron', 'event', 'run exec_plugin_execute_hook');
        $this->checkSubmissionIsRemoved();
    }

    public function testRemovedTranslatedContent()
    {
        $submission = $this->makePreparations();
        $this->prepareTempFile($submission);

        $this->wpcli_exec('cron', 'event', 'run exec_plugin_execute_hook');
        $this->checkSubmissionIsRemoved();
    }
}
