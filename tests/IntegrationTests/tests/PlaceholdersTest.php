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
        $placeholders = [
            'block' => "{$ps}Gutenberg block attribute placeholder{$pe}",
            'inline' => "{$ps}an inline placeholder{$pe}",
            'meta' => "{$ps}can have placeholders{$pe}",
            'title' => "{$ps}title placeholder{$pe}",
        ];
        $content = <<<HTML
<!-- wp:paragraph {"placeholder":"A {$placeholders['block']} and some content"} -->
<p>Some paragraph with {$placeholders['inline']} and some more words.</p>
<!-- /wp:paragraph -->
HTML;
        $expected = <<<HTML
<!-- wp:paragraph {"placeholder":"[Á {$placeholders['block']} ~áñd ~sómé ~cóñ~téñt]"} -->
<p>[S~ómé p~árág~ráph ~wíth {$placeholders['inline']} ~áñd s~ómé m~óré w~órds~.]</p>
<!-- /wp:paragraph -->
HTML;


        $postId = $this->createPost('post', "A {$placeholders['title']}, followed by a title", $content);
        $metaKey = 'someMeta';
        add_post_meta($postId, $metaKey, "Meta values {$placeholders['meta']} as well");


        $submission = $this->getTranslationHelper()->prepareSubmission('post', 1, $postId, 2);
        $submission->getFileUri();
        $submission = $this->getSubmissionManager()->storeEntity($submission);
        $submission = $this->uploadDownload($submission);


        $this->getSiteHelper()->withBlog($submission->getTargetBlogId(), function () use ($expected, $metaKey, $placeholders, $submission) {
            $post = get_post($submission->getTargetId());
            $this->assertEquals("[Á {$placeholders['title']} ~, fól~lówé~d bý á ~títl~é]", $post->post_title);
            $this->assertEquals($expected, $post->post_content);
            $this->assertEquals("[M~étá ~válú~és {$placeholders['meta']} ás ~wéll]", get_post_meta($submission->getTargetId(), $metaKey, true));
        });
    }
}
