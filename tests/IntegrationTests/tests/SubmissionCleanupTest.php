<?php

namespace Smartling\Tests\IntegrationTests\tests;

use Smartling\Helpers\SimpleStorageHelper;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Tests\IntegrationTests\SmartlingUnitTestCaseAbstract;

class SubmissionCleanupTest extends SmartlingUnitTestCaseAbstract
{
    private int $targetBlogId = 2;
    private string $testFile = '/test.php';

    private function makePreparations(): SubmissionEntity
    {
        $postId = $this->createPost();
        $translationHelper = $this->getTranslationHelper();
        $submission = $translationHelper->prepareSubmission('post', 1, $postId, $this->targetBlogId, true);

        /**
         * Check submission status
         */
        $this->assertSame(SubmissionEntity::SUBMISSION_STATUS_NEW, $submission->getStatus());
        $this->assertSame(1, $submission->getIsCloned());
        $this->executeUpload();
        $submissions = $this->getSubmissionManager()->find(
            [
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

    private function prepareTempFile(int $submissionId)
    {

    }

    public function testRemovedOriginalContent()
    {
        self::wpCliExec('plugin', 'activate', 'exec-plugin --network');

        $submission = $this->makePreparations();
        file_put_contents(
            DIR_TESTDATA . $this->testFile,
            "<?php wp_delete_post({$submission->getSourceId()}, true);",
        );

        SimpleStorageHelper::set('execFile', DIR_TESTDATA . $this->testFile);

        self::wpCliExec('cron', 'event', 'run exec_plugin_execute_hook');
        $this->assertNoSubmissions();

        self::wpCliExec('plugin', 'deactivate', 'exec-plugin --network');
    }

    public function testRemovedTranslatedContent()
    {
        self::wpCliExec('plugin', 'activate', 'exec-plugin --network');

        $submission = $this->makePreparations();
        file_put_contents(
            DIR_TESTDATA . $this->testFile,
            "<?php switch_to_blog($this->targetBlogId); wp_delete_post({$submission->getTargetId()}, true);",
        );

        SimpleStorageHelper::set('execFile', DIR_TESTDATA . $this->testFile);

        self::wpCliExec('cron', 'event', 'run exec_plugin_execute_hook');
        $this->assertNoSubmissions();

        self::wpCliExec('plugin', 'deactivate', 'exec-plugin --network');
    }
}
