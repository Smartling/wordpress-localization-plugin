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
}
