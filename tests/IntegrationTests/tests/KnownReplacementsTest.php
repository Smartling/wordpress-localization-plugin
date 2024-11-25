<?php

namespace IntegrationTests\tests;

use Smartling\ContentTypes\ContentTypeHelper;
use Smartling\DbAl\WordpressContentEntities\TaxonomyEntityStd;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Tests\IntegrationTests\SmartlingUnitTestCaseAbstract;

class KnownReplacementsTest extends SmartlingUnitTestCaseAbstract
{
    public function testImageReplacers()
    {
        $sourceBlogId = 1;
        $targetBlogId = 2;
        $postId = $this->createPost(content: <<<HTML
<!-- wp:image {"lightbox":{"enabled":false},"id":22896,"width":"402px","aspectRatio":"1","scale":"contain","sizeSlug":"full","linkDestination":"custom","align":"center","smartlingLockId":"braxo"} -->
<figure class="wp-block-image aligncenter size-full is-resized"><a href="https://example.com"><img src="http://example.com/wp-content/uploads/2024/11/928-200x200-1.jpg" alt="alternative text" class="wp-image-22896" style="aspect-ratio:1;object-fit:contain;width:402px"/></a><figcaption class="wp-element-caption">Caption text</figcaption></figure>
<!-- /wp:image -->
HTML);
        $submission = $this->uploadDownload(
            $this->getTranslationHelper()->prepareSubmission('post', $sourceBlogId, $postId, $targetBlogId)
        );

        $this->assertNotEquals(0, $submission->getTargetId());

        $siteHelper = $this->getSiteHelper();
        $siteHelper->withBlog($targetBlogId, function () use ($siteHelper, $submission) {
            $this->assertEquals(<<<HTML
<!-- wp:image {"lightbox":{"enabled":false},"id":22896,"width":"402px","aspectRatio":"1","scale":"contain","sizeSlug":"full","linkDestination":"custom","align":"center","smartlingLockId":"braxo"} -->
<figure class="wp-block-image aligncenter size-full is-resized"><a href="https://example.com"><img src="http://example.com/wp-content/uploads/2024/11/928-200x200-1.jpg" alt="[á~lté~rñát~ívé t~éxt]" class="wp-image-22896" style="aspect-ratio:1;object-fit:contain;width:402px" /></a><figcaption class="wp-element-caption">[C~ápt~íóñ t~éxt]</figcaption></figure>
<!-- /wp:image -->
HTML, $this->getTargetPost($siteHelper, $submission)->post_content);
        });
    }
}
