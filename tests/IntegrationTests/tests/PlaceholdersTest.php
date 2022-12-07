<?php

namespace Smartling\Tests\IntegrationTests\tests;

use Smartling\Helpers\PlaceholderHelper;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Tests\IntegrationTests\SmartlingUnitTestCaseAbstract;

class PlaceholdersTest extends SmartlingUnitTestCaseAbstract
{
    public function testPlaceholders()
    {
        $ps = PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_START;
        $pe = PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_END;
        $content = <<<HTML
<!-- wp:paragraph {"placeholder":"A {$ps}Gutenberg block attribute placeholder{$pe} and some content","fontSize":"large"} -->
<p>Some paragraph with {$ps}an inline placeholder{$pe} and some more words.</p>
<!-- /wp:paragraph -->
HTML;

        $postId = $this->createPost('post', "A {$ps}title placeholder{$pe}, followed by a title", $content);
        $metaKey = 'someMeta';
        add_post_meta($postId, $metaKey, "Meta values {$ps}can have placeholders{$pe} as well");


        $submission = $this->getTranslationHelper()->prepareSubmission('post', 1, $postId, 2);
        $submission->getFileUri();
        $submission = $this->getSubmissionManager()->storeEntity($submission);
        $submission = $this->uploadDownload($submission);


        $this->getSiteHelper()->withBlog($submission->getTargetBlogId(), function () use ($metaKey, $submission) {
            $post = get_post($submission->getTargetId());
            echo $post->post_title;
            echo $post->post_content;
            var_dump(get_post_meta($submission->getTargetId(), $metaKey, true));
        });
    }

    /**
     * Submit cloned post with attached category which has parent category.
     * Attached only child category but not parent.
     * Expected result: 3 cloned submissions in "Completed" state
     * (1 post, 2 categories).
     */
    public function testSubmitClonedPostWithCategoryWhichHasParentCategory()
    {
        $rootCategoryId = $this->createTerm('Category A');
        $childCategoryId = $this->createTerm('Category B');
        $postId = $this->createPost();
        $this->makeRelationBetweenTerms($rootCategoryId, $childCategoryId);
        $this->addTaxonomyToPost($postId, $childCategoryId);

        $translationHelper = $this->getTranslationHelper();
        $submission = $translationHelper->prepareSubmission('post', 1, $postId, 2, true);

        self::assertSame(SubmissionEntity::SUBMISSION_STATUS_NEW, $submission->getStatus());
        self::assertSame(1, $submission->getIsCloned());

        $this->uploadDownload($submission);

        $submissions = $this->getSubmissionManager()->find(
            [
                'content_type' => 'category',
                // Status will be "Completed" because of an issue in
                // SubmissionEntity::getCompletionPercentage() method. It
                // returns 1 (100%) when total string count and excluded string
                // count equal 0.
                // TODO: fix SubmissionEntity::getCompletionPercentage() and
                // fix this test then (replace SUBMISSION_STATUS_COMPLETED with
                // SUBMISSION_STATUS_IN_PROGRESS).
                'status'       => SubmissionEntity::SUBMISSION_STATUS_COMPLETED,
                'is_cloned'    => 1,
            ]
        );

        self::assertCount(2, $submissions);
    }
}
