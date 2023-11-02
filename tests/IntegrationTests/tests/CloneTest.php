<?php

namespace IntegrationTests\tests;

use Smartling\Helpers\ArrayHelper;
use Smartling\Models\UserCloneRequest;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Tests\IntegrationTests\SmartlingUnitTestCaseAbstract;

class CloneTest extends SmartlingUnitTestCaseAbstract
{
    public function testNoMediaDuplication(): void
    {
        $content = <<<HTML
<!-- wp:paragraph {"smartlingLockId":"test"} -->
<p>Some content</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph {"smartlingLockId":"test2"} -->
<p>Other content</p>
<!-- /wp:paragraph -->
<!-- wp:test/post {"id":%d} /-->
HTML;
        $currentBlogId = get_current_blog_id();
        $targetBlogId = 2;
        switch_to_blog($targetBlogId);
        $attachmentCount = count($this->getAttachments());
        restore_current_blog();

        $childPostId = $this->createPost('post', 'embedded post', 'embedded content');
        $imageId = $this->createAttachment();
        set_post_thumbnail($childPostId, $imageId);
        wp_update_post(['ID' => $imageId, 'post_parent' => $childPostId]); // Force ReferencedStdBasedContentProcessorAbstract change that caused regression initially

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
            $this->assertCount(1, $references->getMissingReferences()[$targetBlogId]['post']);
            $this->assertEquals($childPostId, $references->getMissingReferences()[$targetBlogId]['post'][0]);
            $relationsDiscoveryService->clone(new UserCloneRequest($rootPostId, 'post', [
                1 => [$targetBlogId => ['post' => [$childPostId]]],
                2 => [$targetBlogId => ['attachment' => [$imageId]]],
            ], [$targetBlogId]));
            $this->executeUpload();
        });

        switch_to_blog($targetBlogId);
        $this->assertCount($attachmentCount + 1, $this->getAttachments(), 'Expected exactly one more attachment in target blog after cloning');
        $rootSubmission = ArrayHelper::first($this->getSubmissionManager()->find([SubmissionEntity::FIELD_SOURCE_BLOG_ID => $currentBlogId, SubmissionEntity::FIELD_SOURCE_ID => $rootPostId]));
        $childSubmission = ArrayHelper::first($this->getSubmissionManager()->find([SubmissionEntity::FIELD_SOURCE_BLOG_ID => $currentBlogId, SubmissionEntity::FIELD_SOURCE_ID => $childPostId]));
        $imageSubmission = ArrayHelper::first($this->getSubmissionManager()->find([SubmissionEntity::FIELD_SOURCE_BLOG_ID => $currentBlogId, SubmissionEntity::FIELD_SOURCE_ID => $imageId]));
        $this->assertInstanceOf(SubmissionEntity::class, $rootSubmission);
        $this->assertInstanceOf(SubmissionEntity::class, $childSubmission);
        $this->assertInstanceOf(SubmissionEntity::class, $imageSubmission);
        $targetRootPostId = $rootSubmission->getTargetId();
        $childPostTargetId = $childSubmission->getTargetId();
        $targetChildPostId = $childPostTargetId;
        $post = get_post($rootSubmission->getTargetId());
        $this->assertEquals(sprintf($content, $childPostTargetId), $post->post_content, 'Expected root post to reference child post id at the target blog');
        $this->assertEquals($addedMetaValue, get_post_meta($rootSubmission->getTargetId(), $addedMetaKey, true), 'Expected boolean values in array metadata to be preserved');
        $imageTargetId = $imageSubmission->getTargetId();
        $this->assertEquals($imageTargetId, get_post_meta($childPostTargetId, '_thumbnail_id', true), 'Expected child post to reference attachment id at the target blog');
        $this->assertNotEquals($childPostId, $childPostTargetId, 'Expected child post id to change in translation');
        $this->assertNotEquals($imageId, $imageTargetId, 'Expected attachment id to change in translation');
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
        $post->post_content = str_replace($search, $replace, $post->post_content);
        $this->assertEquals($rootSubmission->getTargetId(), wp_insert_post($post->to_array()));
        $post = get_post($rootSubmission->getTargetId());
        $this->assertEquals(str_replace($search, $replace, sprintf($content, $childPostTargetId)), $post->post_content, 'Expected lock to be added');
        restore_current_blog();
        $relationsDiscoveryService->clone(new UserCloneRequest($rootPostId, 'post', [], [$targetBlogId]));
        $this->executeUpload();
        switch_to_blog($targetBlogId);
        $post = get_post($targetRootPostId);
        $this->assertEquals(str_replace($search, $replace, sprintf($content, $targetChildPostId)), $post->post_content, 'Expected changed content to be preserved');
        restore_current_blog();
    }

    private function getAttachments(): array
    {
        self::flush_cache();
        return get_posts(['post_type' => 'attachment']);
    }
}
