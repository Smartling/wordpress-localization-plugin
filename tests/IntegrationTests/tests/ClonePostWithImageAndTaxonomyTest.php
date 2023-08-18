<?php

namespace IntegrationTests\tests;

use Smartling\ContentTypes\ContentTypeHelper;
use Smartling\DbAl\WordpressContentEntities\TaxonomyEntityStd;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Tests\IntegrationTests\SmartlingUnitTestCaseAbstract;

class ClonePostWithImageAndTaxonomyTest extends SmartlingUnitTestCaseAbstract
{
    public function testComplexClone()
    {
        /**
         * Creating taxonomy
         */
        $categoryName = 'Category A';
        $category = wp_insert_term($categoryName, 'category');
        $categoryId = $category['term_id'];
        $wrapper = new TaxonomyEntityStd('category');
        $cat = $wrapper->get($categoryId);
        $this->assertEquals($categoryName, $cat->name);
        $this->assertEquals($categoryId, $cat->term_id);
        $sourceBlogId = 1;
        $targetBlogId = 2;
        $postId = $this->createPost();
        $rootPostId = $this->createPost();
        wp_update_post(['ID' => $postId, 'post_parent' => $rootPostId]);

        /**
         * Adding post to taxonomy
         */
        wp_set_object_terms($postId, $cat->term_id, 'category');

        $imageId = $this->createAttachment();
        set_post_thumbnail($postId, $imageId);
        $this->assertTrue(has_post_thumbnail($postId));

        $translationHelper = $this->getTranslationHelper();

        $rootSubmission = $translationHelper->prepareSubmission('post', $sourceBlogId, $rootPostId, $targetBlogId, true);
        $this->uploadDownload($rootSubmission);
        $translationHelper->prepareSubmission('category', $sourceBlogId, $categoryId, $targetBlogId, true);
        $translationHelper->prepareSubmission(ContentTypeHelper::POST_TYPE_ATTACHMENT, $sourceBlogId, $imageId, $targetBlogId, true);
        $submission = $translationHelper->prepareSubmission('post', $sourceBlogId, $postId, $targetBlogId, true);

        /**
         * Check submission status
         */
        $this->assertSame(SubmissionEntity::SUBMISSION_STATUS_NEW, $submission->getStatus());
        $this->assertSame(1, $submission->getIsCloned());

        $this->uploadDownload($submission);

        $postSubmissions = $this->getSubmissionManager()->find([
            'content_type' => 'post',
            'is_cloned' => 1,
        ]);
        $this->assertCount(2, $postSubmissions);
        $postSubmission = null;
        foreach ($postSubmissions as $submission) {
            switch($submission->getSourceId()) {
                case $rootPostId:
                    $rootSubmission = $submission;
                    break;
                case $postId:
                    $postSubmission = $submission;
                    break;
            }
        }
        $this->assertNotNull($postSubmission);

        $this->assertCount(1, $this->getSubmissionManager()->find(
            [
                'content_type' => 'attachment',
                'is_cloned' => 1,
            ]));
        $this->assertCount(1, $this->getSubmissionManager()->find(
            [
                'content_type' => 'category',
                'is_cloned' => 1,
            ]));
        switch_to_blog($targetBlogId);
        $this->assertEquals($rootSubmission->getTargetId(), get_post($postSubmission->getTargetId())->post_parent);
        restore_current_blog();
    }
}
