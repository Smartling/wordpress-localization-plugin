<?php

namespace IntegrationTests\tests;

use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\SiteHelper;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Tests\IntegrationTests\SmartlingUnitTestCaseAbstract;

class NewCategoryIsAttachedToPostOnDownloadTest extends SmartlingUnitTestCaseAbstract
{

    public function testSubmitPostWithCategoryWhichHasParentCategory()
    {
        $categoryId = $this->createTerm('New category');

        $postId = $this->createPost();

        $this->addTaxonomyToPost($postId, $categoryId);

        $translationHelper = $this->getTranslationHelper();
        $submission = $translationHelper->prepareSubmission('post', 1, $postId, 2);

        $submission->getFileUri();
        $submission = $this->getSubmissionManager()->storeEntity($submission);

        self::assertSame(SubmissionEntity::SUBMISSION_STATUS_NEW, $submission->getStatus());
        self::assertSame(0, $submission->getIsCloned());

        $this->uploadDownload($submission);

        $submissions = $this->getSubmissionManager()->find(
            [
                'content_type' => 'category',
                'status'       => SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS,
                'is_cloned'    => 0,
            ]
        );

        self::assertCount(1, $submissions);

        /**
         * @var SubmissionEntity $submission
         */
        $submission = ArrayHelper::first($submissions);
        $targetCategoryId = (int) $submission->getTargetId();

        $submissions = $this->getSubmissionManager()->find(
            [
                'content_type' => 'post',
                'status'       => SubmissionEntity::SUBMISSION_STATUS_COMPLETED,
                'is_cloned'    => 0,
                'source_id'    => $postId,
            ]
        );

        self::assertCount(1, $submissions);

        /**
         * @var SubmissionEntity $submission
         */
        $submission = ArrayHelper::first($submissions);
        $targetPostId = $submission->getTargetId();

        /**
         * @var SiteHelper $siteHelper
         */
        $siteHelper = $this->get('site.helper');

        $curBlogId = $siteHelper->getCurrentBlogId();
        $targetBlogId = $submission->getTargetBlogId();

        $needChange = false;

        if ($targetBlogId !== $curBlogId) {
            $needChange = true;
        }

        if ($needChange) {
            $siteHelper->switchBlogId($targetBlogId);
        }

        $terms = wp_get_post_terms( $targetPostId, 'category' );

        if ($needChange) {
            $siteHelper->restoreBlogId();
        }

        self::assertCount(1, $terms);

        $term = ArrayHelper::first($terms);

        self::assertSame($targetCategoryId, $term->term_id);
    }

}