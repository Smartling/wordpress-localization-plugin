<?php

namespace IntegrationTests\tests;

use Smartling\ContentTypes\ContentTypeHelper;
use Smartling\Tests\IntegrationTests\SmartlingUnitTestCaseAbstract;

class ShortcodeTest extends SmartlingUnitTestCaseAbstract {
    public function testShortcodeTranslation()
    {
        $sourceBlogId = 1;
        $targetBlogId = 2;
        $imageId = $this->createAttachment();
        $imageSubmission = $this->uploadDownload($this->getTranslationHelper()
            ->prepareSubmission(ContentTypeHelper::POST_TYPE_ATTACHMENT, $sourceBlogId, $imageId, $targetBlogId));
        $this->assertNotNull($imageSubmission);

        $postId = $this->createPost(
            title: 'Shortcode test',
            content: sprintf(
                '[caption id="attachment_%1$d" align="alignright" width=184]<img class="size-medium wp-image-%1$d" title="The Great Wave" src="%2$s" alt="Kanagawa" width="184" height="300"/> The Great Wave[/caption]',
                $imageId,
                get_attachment_link($imageId),
            ),
        );
        $submission = $this->uploadDownload(
            $this->getTranslationHelper()->prepareSubmission('post', $sourceBlogId, $postId, $targetBlogId),
        );
        $this->assertNotNull($submission);

        $this->getSiteHelper()->withBlog($targetBlogId, function () use ($imageSubmission, $submission) {
            $this->assertEquals(sprintf(
                '[caption id="attachment_%1$d" align="alignright" width=184]<img class="size-medium wp-image-%1$d" title="[T~hé Gr~éát W~ávé]" src="%2$s" alt="[K~áñág~áwá]" width="184" height="300" /> T~hé G~réát ~Wáv~é[/caption]',
                $imageSubmission->getSourceId(),
                get_attachment_link($imageSubmission->getTargetId()),
            ), get_post($submission->getTargetId())->post_content);
        });
    }
}
