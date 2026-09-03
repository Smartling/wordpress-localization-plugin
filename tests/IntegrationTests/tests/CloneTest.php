<?php

namespace IntegrationTests\tests;

use Smartling\Helpers\DateTimeHelper;
use Smartling\Jobs\JobEntity;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Tests\IntegrationTests\SmartlingUnitTestCaseAbstract;
use Smartling\Vendor\Smartling\Exceptions\SmartlingApiException;

class CloneTest extends SmartlingUnitTestCaseAbstract {
    public function testLocking(): void
    {
        $content = <<<HTML
<!-- wp:paragraph {"smartlingLockId":"test"} -->
<p>Some content</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph {"smartlingLockId":"test2"} -->
<p>Other content</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph {"smartlingLockId":"test3"} -->
<p>Third content</p>
<!-- /wp:paragraph -->
HTML;
        $currentBlogId = get_current_blog_id();
        $metaKey = 'metakey';
        $metaValue = 'metavalue';
        $metaValueChanged = 'metavalue changed';
        $targetBlogId = 2;
        $this->assertNotEquals($currentBlogId, $targetBlogId);
        $title = 'Cloning Locking Test';
        $titleChanged = 'Cloning Locking Test Changed';
        $postId = $this->createPost(title: $title, content: $content);
        $this->assertIsInt($postId);

        $search = <<<HTML
<!-- wp:paragraph {"smartlingLockId":"test2"} -->
<p>Other content</p>
<!-- /wp:paragraph -->
HTML;
        $replace = <<<HTML
<!-- wp:paragraph {"smartlingLockId":"test2","smartlingLocked":true} -->
<p>Other content changed</p>
<!-- /wp:paragraph -->
HTML;

        add_post_meta($postId, $metaKey, $metaValue);

        $submission = $this->createSubmission('post', $postId, $currentBlogId, $targetBlogId);
        $submission->setIsCloned(1);
        $submission = $this->getSubmissionManager()->storeEntity($submission);
        $this->executeUpload();
        $submission = $this->getSubmissionById($submission->getId());

        $this->getSiteHelper()->withBlog($targetBlogId, function () use ($content, $metaKey, $metaValue, $search, $submission, $replace, $title) {
            $post = $this->assertPostValues($content, $metaKey, $metaValue, $title, $submission->getTargetId());
            $post->post_content = str_replace($search, $replace, $post->post_content);
            $this->assertEquals($submission->getTargetId(), wp_insert_post($post->to_array()));
            $post = get_post($submission->getTargetId());
            $this->assertEquals(str_replace($search, $replace, $content), $post->post_content, 'Expected lock to be added');
        });

        $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_NEW);
        $submission = $this->getSubmissionManager()->storeEntity($submission);
        $this->executeUpload();

        $this->getSiteHelper()->withBlog($targetBlogId, function () use ($content, $metaKey, $metaValue, $metaValueChanged, $search, $submission, $replace, $title, $titleChanged) {
            $post = $this->assertPostValues(str_replace($search, $replace, $content), $metaKey, $metaValue, $title, $submission->getTargetId());
            $post->post_title = $titleChanged;
            $this->assertEquals($submission->getTargetId(), wp_insert_post($post->to_array()));
            update_post_meta($submission->getTargetId(), $metaKey, $metaValueChanged);
            $this->assertEquals($metaValueChanged, get_post_meta($submission->getTargetId(), $metaKey, true));
        });

        $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_NEW);
        $submission->setLockedFields(['entity/post_title', "meta/$metaKey"]);
        $submission = $this->getSubmissionManager()->storeEntity($submission);
        $this->executeUpload();

        $this->getSiteHelper()->withBlog($targetBlogId, function () use ($content, $metaKey, $metaValueChanged, $search, $submission, $replace, $titleChanged) {
            $this->assertPostValues(str_replace($search, $replace, $content), $metaKey, $metaValueChanged, $titleChanged, $submission->getTargetId());
        });
    }

    public function testIsClonedClearedOnTranslation(): void
    {
        $currentBlogId = get_current_blog_id();
        $targetBlogId = 2;
        $this->assertNotEquals($currentBlogId, $targetBlogId);
        $postId = $this->createPost(title: 'Clear cloned flag', content: 'Post content');
        $contentType = 'post';
        $submission = $this->createSubmission($contentType, $postId, $currentBlogId, $targetBlogId);
        $submission->setIsCloned(1);
        $submission = $this->getSubmissionManager()->storeEntity($submission);
        $submission = $this->getSubmissionManager()->getEntityById($submission->getId());
        $this->assertTrue($submission->isCloned());
        $apiWrapper = $this->getApiWrapper();
        $jobName = 'testIsClonedClearedOnTranslation';
        $profile = $this->getProfileById(1);
        $response = $apiWrapper->listJobs($profile, $jobName);
        $jobUid = $response['items'][0]['translationJobUid'] ?? null;
        $jobDescription = 'Test job';

        if ($jobUid === null) {
            try {
                $result = $apiWrapper->createJob($profile, [
                    'name' => $jobName,
                    'description' => $jobDescription,
                ]);
            } catch (SmartlingApiException) {
                $jobName = $jobName(' ' . date(DateTimeHelper::getWordpressTimeFormat()));
                $result = $apiWrapper->createJob($profile, [
                    'name' => $jobName,
                    'description' => $jobDescription,
                ]);
            }

            $jobUid = $result['translationJobUid'];
        }

        $this->getContentRelationsDiscoveryService()->bulkUpload(
            false,
            [$postId],
            $contentType,
            $currentBlogId,
            new JobEntity($jobName, $jobUid, $profile->getProjectId()),
            $profile,
            [$targetBlogId],
        );
        $submission = $this->getSubmissionManager()->getEntityById($submission->getId());
        $this->assertFalse($submission->isCloned());
    }

    private function assertPostValues(string $expectedContent, string $expectedMetaKey, string $expectedMetaValue, string $expectedTitle, int $id): \WP_Post
    {
        $post = get_post($id);
        $this->assertEquals($expectedContent, $post->post_content);
        $this->assertEquals($expectedTitle, $post->post_title);
        $this->assertEquals(get_post_meta($id, $expectedMetaKey, true), $expectedMetaValue);

        return $post;
    }
}
