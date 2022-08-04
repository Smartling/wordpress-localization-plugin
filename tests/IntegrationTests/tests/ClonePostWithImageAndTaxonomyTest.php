<?php

namespace IntegrationTests\tests;

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
        /**
         * Creating post
         */
        $postId = $this->createPost();
        /**
         * Adding post to taxonomy
         */
        wp_set_object_terms($postId, $cat->term_id, 'category');

        $imageId = $this->createAttachment();
        set_post_thumbnail($postId, $imageId);
        $this->assertTrue(has_post_thumbnail($postId));

        $translationHelper = $this->getTranslationHelper();

        $submission = $translationHelper->prepareSubmission('post', 1, $postId, 2, true);

        /**
         * Check submission status
         */
        $this->assertSame(SubmissionEntity::SUBMISSION_STATUS_NEW, $submission->getStatus());
        $this->assertSame(1, $submission->getIsCloned());

        $this->uploadDownload($submission);

        $this->assertCount(1, $this->getSubmissionManager()->find(
            [
                'content_type' => 'post',
                'is_cloned'    => 1,
            ]));

        $this->assertCount(1, $this->getSubmissionManager()->find(
            [
                'content_type' => 'attachment',
                'is_cloned'    => 1,
            ]));
        $this->assertCount(1, $this->getSubmissionManager()->find(
            [
                'content_type' => 'category',
                'is_cloned'    => 1,
            ]));
    }
}
