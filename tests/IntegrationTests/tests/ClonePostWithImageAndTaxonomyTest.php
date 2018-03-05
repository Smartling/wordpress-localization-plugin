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
        self::assertTrue($cat->getName() === $categoryName);
        /**
         * Creating post
         */
        $postId = $this->createPost();
        /**
         * Adding post to taxonomy
         */
        $result = wp_set_object_terms($postId, [$cat->getId()], 'category');

        /**
         * wp_set_object_terms doesn't work in current mode,
         * so raw table update is used
         */
        $queryTemplate = "REPLACE INTO `%sterm_relationships` VALUES('%s', '%s', 0)";
        global $wpdb;
        /**
         * @var \wpdb $wpdb
         */
        $query = vsprintf($queryTemplate, [$wpdb->base_prefix, $postId, $categoryId]);
        $wpdb->query($query);

        $imageId = $this->createAttachment();
        set_post_thumbnail($postId, $imageId);
        $this->assertTrue(has_post_thumbnail($postId));

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

        $this->uploadDownload($submission);

        $submissions = $this->getSubmissionManager()->find(
            [
                'content_type' => 'post',
                'is_cloned'    => 1,
            ]);

        $this->assertTrue(1 === count($submissions));

        $submissions = $this->getSubmissionManager()->find(
            [
                'content_type' => 'attachment',
                'is_cloned'    => 1,
            ]);

        $this->assertTrue(1 === count($submissions));
        $submissions = $this->getSubmissionManager()->find(
            [
                'content_type' => 'category',
                'is_cloned'    => 1,
            ]);

        $this->assertTrue(1 === count($submissions));
    }
}