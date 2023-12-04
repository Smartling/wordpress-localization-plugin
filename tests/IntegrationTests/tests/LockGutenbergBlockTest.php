<?php

namespace IntegrationTests\tests;

use Smartling\Tests\IntegrationTests\SmartlingUnitTestCaseAbstract;

class LockGutenbergBlockTest extends SmartlingUnitTestCaseAbstract
{
    public function testLockPostFlow()
    {
        $content = <<<HTML
<!-- wp:paragraph {"smartlingLockId":"jqfqx"} -->
<p>Paragraph 1</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"smartlingLockId":"xtqin"} -->
<p>Paragraph 2</p>
<!-- /wp:paragraph -->

<!-- wp:columns {"smartlingLockId":"fozqx"} -->
<div class="wp-block-columns"><!-- wp:column {"smartlingLockId":"ldhbf"} -->
<div class="wp-block-column"></div>
<!-- /wp:column -->

<!-- wp:column {"smartlingLockId":"bigml"} -->
<div class="wp-block-column"></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->
HTML;

        $postId = $this->createPost(content: $content);
        $submission = $this->createSubmission('post', $postId);
        $submission = $this->getSubmissionManager()->storeEntity($submission);
        $submission = $this->uploadDownload($submission);

        $this->getSiteHelper()->withBlog($submission->getTargetBlogId(), function () use ($submission) {
            $this->assertEquals(<<<HTML
<!-- wp:paragraph {"smartlingLockId":"jqfqx"} -->
<p>[P~árá~grá~ph 1]</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"smartlingLockId":"xtqin"} -->
<p>[P~árá~grá~ph 2]</p>
<!-- /wp:paragraph -->

<!-- wp:columns {"smartlingLockId":"fozqx"} -->
<div class="wp-block-columns"><!-- wp:column {"smartlingLockId":"ldhbf"} -->
<div class="wp-block-column"></div>
<!-- /wp:column -->

<!-- wp:column {"smartlingLockId":"bigml"} -->
<div class="wp-block-column"></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->
HTML, get_post($submission->getTargetId())->post_content);
            $this->editPost([
                'ID' => $submission->getTargetId(),
                'post_content' => <<<HTML
<!-- wp:paragraph {"smartlingLockId":"jqfqx"} -->
<p>Paragraph 1 changed</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"smartlingLocked":true,"smartlingLockId":"xtqin"} -->
<p>Paragraph 2 changed and locked</p>
<!-- /wp:paragraph -->

<!-- wp:columns {"smartlingLockId":"fozqx"} -->
<div class="wp-block-columns"><!-- wp:column {"smartlingLockId":"ldhbf"} -->
<div class="wp-block-column"></div>
<!-- /wp:column -->

<!-- wp:column {"smartlingLockId":"bigml"} -->
<div class="wp-block-column"><!-- wp:paragraph {"smartlingLocked":true,"smartlingLockId":"iblhh"} -->
<p>Locked nested paragraph that didn't exist in source</p>
<!-- /wp:paragraph --></div>
<!-- /wp:columns -->
HTML,
            ]);
        });

        $this->forceSubmissionDownload($submission);

        $this->getSiteHelper()->withBlog($submission->getTargetBlogId(), function () use ($submission) {
            $this->assertEquals(<<<HTML
<!-- wp:paragraph {"smartlingLockId":"jqfqx"} -->
<p>[P~árá~grá~ph 1]</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"smartlingLocked":true,"smartlingLockId":"xtqin"} -->
<p>Paragraph 2 changed and locked</p>
<!-- /wp:paragraph -->

<!-- wp:columns {"smartlingLockId":"fozqx"} -->
<div class="wp-block-columns"><!-- wp:column {"smartlingLockId":"ldhbf"} -->
<div class="wp-block-column"></div>
<!-- /wp:column -->

<!-- wp:column {"smartlingLockId":"bigml"} -->
<div class="wp-block-column"></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->
HTML, get_post($submission->getTargetId())->post_content);
        });
    }
}
