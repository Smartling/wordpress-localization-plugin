<?php

namespace IntegrationTests\tests;

use Smartling\Helpers\ArrayHelper;
use Smartling\Models\CloneRequest;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Tests\IntegrationTests\SmartlingUnitTestCaseAbstract;

class CloneTest extends SmartlingUnitTestCaseAbstract
{
    public function testNoMediaDuplicationOnCloning(): void
    {
        $currentBlogId = get_current_blog_id();
        $targetBlogId = 2;
        switch_to_blog($targetBlogId);
        $attachmentCount = count($this->getAttachments());
        restore_current_blog();

        $childPostId = $this->createPost('post', 'embedded post', 'embedded content');
        $imageId = $this->createAttachment();
        set_post_thumbnail($childPostId, $imageId);
        wp_update_post(['ID' => $imageId, 'post_parent' => $childPostId]);

        $rootPostId = $this->createPost('post', 'root post', "<!-- wp:test/post {\"id\":$childPostId} /-->");

        $this->withBlockRules($this->getRulesManager(), [
            'test' => [
                'block' => 'test/post',
                'path' => 'id',
                'replacerId' => 'related|post',
            ],
        ], function () use ($childPostId, $imageId, $rootPostId, $targetBlogId) {
            $relationsDiscoveryService = $this->getContentRelationsDiscoveryService();
            $references = $relationsDiscoveryService->getRelations('post', $rootPostId, [$targetBlogId]);
            $this->assertCount(1, $references->getMissingReferences()[$targetBlogId]['post']);
            $this->assertEquals($childPostId, $references->getMissingReferences()[$targetBlogId]['post'][0]);
            $relationsDiscoveryService->clone(new CloneRequest($rootPostId, 'post', [
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
        $childPostTargetId = $childSubmission->getTargetId();
        $this->assertEquals('<!-- wp:test/post {"id":' . $childPostTargetId . '} /-->', get_post($rootSubmission->getTargetId())->post_content, 'Expected root post to reference child post id at the target blog');
        $imageTargetId = $imageSubmission->getTargetId();
        $this->assertEquals($imageTargetId, get_post_meta($childPostTargetId, '_thumbnail_id', true), 'Expected child post to reference attachment id at the target blog');
        $this->assertNotEquals($childPostId, $childPostTargetId, 'Expected child post id to change in translation');
        $this->assertNotEquals($imageId, $imageTargetId, 'Expected attachment id to change in translation');
        restore_current_blog();
    }

    private function getAttachments(): array
    {
        self::flush_cache();
        return get_posts(['post_type' => 'attachment']);
    }
}
