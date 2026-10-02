<?php

namespace Smartling\Tests\IntegrationTests;

use Smartling\FTS\FtsService;
use Smartling\Submissions\SubmissionEntity;

/**
 * Integration tests for Fast Translation Service (FTS / Instant Translation).
 *
 * These tests make real Smartling API calls and require valid credentials in
 * tests/.env.local:
 *   CRE_PROJECT_ID, CRE_USER_IDENTIFIER, CRE_TOKEN_SECRET, SITES
 *
 * Run selectively:
 *   ./run-integration-tests.sh --filter FtsIntegrationTest
 *
 * Translation typically completes within 2 minutes.
 */
class FtsIntegrationTest extends SmartlingUnitTestCaseAbstract
{
    private const POLL_TIMEOUT_SECONDS = 120;
    private const POLL_SLEEP_SECONDS = 5;

    private FtsService $ftsService;

    public function setUp(): void
    {
        parent::setUp();
        $this->ftsService = $this->get('fts.service');
    }

    /**
     * Full FTS workflow: upload, translate, poll, download, apply.
     * Verifies that a post is translated and the translated content appears in the target blog.
     */
    public function testFullFtsWorkflow(): void
    {
        $sourceContent = 'Hello world. This is a test post for instant translation.';
        $postId = $this->createPost('post', 'FTS Integration Test Post', $sourceContent);
        $this->assertGreaterThan(0, $postId, 'Post creation failed');

        $submission = $this->createSubmission('post', $postId, 1, 2);
        $submission = $this->getSubmissionManager()->storeEntity($submission);
        $this->assertNotNull($submission->getId(), 'Submission store failed');

        // Initiate non-blocking FTS request
        $result = $this->ftsService->requestInstantTranslationBatch([$submission]);
        $this->assertTrue($result['success'], 'FTS batch request failed: ' . ($result['message'] ?? 'unknown error'));
        $this->assertArrayHasKey('fileUid', $result);
        $this->assertArrayHasKey('mtUid', $result);
        $this->assertNotEmpty($result['fileUid']);
        $this->assertNotEmpty($result['mtUid']);

        // Re-fetch submission to verify fileUid:mtUid was stored
        $submission = $this->getSubmissionById($submission->getId());
        $this->assertNotNull($submission, 'Could not re-fetch submission');
        $this->assertNotEmpty($submission->getFileUri(), 'fileUid:mtUid was not stored in submission.file_uri');
        $this->assertStringContainsString(':', $submission->getFileUri(), 'file_uri should be in fileUid:mtUid format');

        // Poll until completed or timeout
        $finalStatus = $this->pollUntilDone($submission);

        $this->assertEquals('completed', $finalStatus['status'],
            'FTS translation did not complete within ' . self::POLL_TIMEOUT_SECONDS . ' seconds. ' .
            'Last status: ' . ($finalStatus['status'] ?? 'unknown') . '. ' .
            'Message: ' . ($finalStatus['message'] ?? '')
        );

        // Verify submission was marked as completed in the database
        $completedSubmission = $this->getSubmissionById($submission->getId());
        $this->assertNotNull($completedSubmission);
        $this->assertEquals(
            SubmissionEntity::SUBMISSION_STATUS_COMPLETED,
            $completedSubmission->getStatus(),
            'Submission status was not updated to COMPLETED'
        );
        $this->assertGreaterThan(0, $completedSubmission->getTargetId(),
            'Target post was not created in the target blog'
        );

        // Verify the translated content exists in the target blog
        $targetPost = $this->getTargetPost($this->getSiteHelper(), $completedSubmission);
        $this->assertNotNull($targetPost, 'Target post not found in target blog');
        $this->assertNotEmpty($targetPost->post_content, 'Translated post content is empty');
    }

    /**
     * Verifies that requestInstantTranslationBatch enforces the same-source constraint.
     */
    public function testBatchRejectsSubmissionsFromDifferentSources(): void
    {
        $postId1 = $this->createPost('post', 'Source Post 1', 'Content 1');
        $postId2 = $this->createPost('post', 'Source Post 2', 'Content 2');

        $submission1 = $this->getSubmissionManager()->storeEntity(
            $this->createSubmission('post', $postId1, 1, 2)
        );
        $submission2 = $this->getSubmissionManager()->storeEntity(
            $this->createSubmission('post', $postId2, 1, 2)
        );

        // Two different source posts: should be rejected by the batch method
        $result = $this->ftsService->requestInstantTranslationBatch([$submission1, $submission2]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Same source', $result['message']);
    }

    /**
     * Verifies that checkAndApplyTranslation returns an error for a submission
     * that has no fileUid:mtUid stored.
     */
    public function testCheckStatusFailsWithoutFileUri(): void
    {
        $postId = $this->createPost('post', 'Post Without FTS', 'Some content');
        $submission = $this->getSubmissionManager()->storeEntity(
            $this->createSubmission('post', $postId, 1, 2)
        );

        // file_uri is empty at this point (FTS not requested)
        $result = $this->ftsService->checkAndApplyTranslation($submission);

        $this->assertEquals('error', $result['status']);
        $this->assertArrayHasKey('message', $result);
    }

    /**
     * Polls FTS status until completed/failed/error or timeout.
     */
    private function pollUntilDone(SubmissionEntity $submission): array
    {
        $start = time();
        $lastResult = ['status' => 'unknown'];

        $this->getLogger()->info(sprintf(
            'FtsIntegrationTest: Starting poll for submission %d (fileUri=%s)',
            $submission->getId(),
            $submission->getFileUri()
        ));

        while ((time() - $start) < self::POLL_TIMEOUT_SECONDS) {
            $result = $this->ftsService->checkAndApplyTranslation($submission);
            $lastResult = $result;

            $this->getLogger()->info(sprintf(
                'FtsIntegrationTest: Poll result for submission %d: status=%s',
                $submission->getId(),
                $result['status']
            ));

            if (in_array($result['status'], ['completed', 'failed', 'error'], true)) {
                return $result;
            }

            sleep(self::POLL_SLEEP_SECONDS);
        }

        $this->getLogger()->warning(sprintf(
            'FtsIntegrationTest: Polling timed out after %d seconds for submission %d',
            self::POLL_TIMEOUT_SECONDS,
            $submission->getId()
        ));

        return array_merge($lastResult, ['status' => 'timeout']);
    }
}
