<?php

namespace IntegrationTests\tests;

use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\DateTimeHelper;
use Smartling\Jobs\JobEntity;
use Smartling\Models\UserCloneRequest;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Tests\IntegrationTests\SmartlingUnitTestCaseAbstract;
use Smartling\Vendor\Smartling\Exceptions\SmartlingApiException;

class CloneTest extends SmartlingUnitTestCaseAbstract {
    public function testNoMediaDuplication(): void
    {
        $this->markTestSkipped('TODO');
        $content = '<!-- wp:test/post {"id":%d} /-->';
        $currentBlogId = get_current_blog_id();
        $targetBlogId = 2;
        switch_to_blog($targetBlogId);
        $attachmentCount = count($this->getAttachments());
        restore_current_blog();

        $childPostId = $this->createPost('post', 'embedded post', 'embedded content');
        $imageId = $this->createAttachment();
        set_post_thumbnail($childPostId, $imageId);
        wp_update_post([
            'ID' => $imageId,
            'post_parent' => $childPostId,
        ]); // Force ReferencedStdBasedContentProcessorAbstract change that caused regression initially

        $relationsDiscoveryService = $this->getContentRelationsDiscoveryService();
        $rootPostId = $this->createPost('post', 'root post', sprintf($content, $childPostId));
        $addedMetaKey = 'contribute_slug_to_childpage_url';
        $addedMetaValue = [
            'use_page_name' => true,
            $addedMetaKey => false,
        ];
        add_post_meta($rootPostId, $addedMetaKey, $addedMetaValue);

        $this->withBlockRules($this->getRulesManager(), [
            'test' => [
                'block' => 'test/post',
                'path' => 'id',
                'replacerId' => 'related|post',
            ],
        ], function () use ($childPostId, $imageId, $relationsDiscoveryService, $rootPostId, $targetBlogId) {
            $references = $relationsDiscoveryService->getRelations('post', $rootPostId, [$targetBlogId]);
            $postReferences = array_filter($references->getReferences(), static fn($rel) => $rel->getContentType() === 'post');
            $this->assertCount(1, $postReferences);
            $this->assertEquals($childPostId, $postReferences[0]->getId());
            $relationsDiscoveryService->clone(new UserCloneRequest($rootPostId, 'post', [
                $targetBlogId => [
                    'post' => [$childPostId],
                    'attachment' => [$imageId],
                ],
            ], [$targetBlogId]));
            $this->executeUpload();
        });

        switch_to_blog($targetBlogId);
        $this->assertCount($attachmentCount + 1, $this->getAttachments(), 'Expected exactly one more attachment in target blog after cloning');
        $rootSubmission = ArrayHelper::first($this->getSubmissionManager()->find([
            SubmissionEntity::FIELD_SOURCE_BLOG_ID => $currentBlogId,
            SubmissionEntity::FIELD_SOURCE_ID => $rootPostId,
        ]));
        $childSubmission = ArrayHelper::first($this->getSubmissionManager()->find([
            SubmissionEntity::FIELD_SOURCE_BLOG_ID => $currentBlogId,
            SubmissionEntity::FIELD_SOURCE_ID => $childPostId,
        ]));
        $imageSubmission = ArrayHelper::first($this->getSubmissionManager()->find([
            SubmissionEntity::FIELD_SOURCE_BLOG_ID => $currentBlogId,
            SubmissionEntity::FIELD_SOURCE_ID => $imageId,
        ]));
        $this->assertInstanceOf(SubmissionEntity::class, $rootSubmission);
        $this->assertInstanceOf(SubmissionEntity::class, $childSubmission);
        $this->assertInstanceOf(SubmissionEntity::class, $imageSubmission);
        $childPostTargetId = $childSubmission->getTargetId();
        $post = get_post($rootSubmission->getTargetId());
        $this->assertEquals(sprintf($content, $childPostTargetId), $post->post_content, 'Expected root post to reference child post id at the target blog');
        $this->assertEquals($addedMetaValue, get_post_meta($rootSubmission->getTargetId(), $addedMetaKey, true), 'Expected boolean values in array metadata to be preserved');
        $imageTargetId = $imageSubmission->getTargetId();
        $this->assertEquals($imageTargetId, get_post_meta($childPostTargetId, '_thumbnail_id', true), 'Expected child post to reference attachment id at the target blog');
        $this->assertNotEquals($childPostId, $childPostTargetId, 'Expected child post id to change in translation');
        $this->assertNotEquals($imageId, $imageTargetId, 'Expected attachment id to change in translation');
        restore_current_blog();
    }

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

    private function getAttachments(): array
    {
        self::flush_cache();

        return get_posts(['post_type' => 'attachment']);
    }
}
