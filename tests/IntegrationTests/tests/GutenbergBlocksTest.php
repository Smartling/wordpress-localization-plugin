<?php

namespace IntegrationTests\tests;

use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\SiteHelper;
use Smartling\Helpers\TranslationHelper;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;
use Smartling\Tests\IntegrationTests\SmartlingUnitTestCaseAbstract;
use Smartling\Tuner\MediaAttachmentRulesManager;

class GutenbergBlocksTest extends SmartlingUnitTestCaseAbstract
{
    private MediaAttachmentRulesManager $rulesManager;
    private SiteHelper $siteHelper;
    private SubmissionManager $submissionManager;
    private TranslationHelper $translationHelper;
    private int $sourceBlogId = 1;
    private int $targetBlogId = 2;

    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->rulesManager = $this->getRulesManager();
        $this->siteHelper = $this->getSiteHelper();
        $this->submissionManager = $this->getSubmissionManager();
        $this->translationHelper = $this->getTranslationHelper();
    }

    public function testAttributesLocking()
    {
        $postId = $this->createPost(title: 'Block attributes locking test', content: <<<HTML
<!-- wp:columns {"smartlingLockId":"columns"} -->
<div class="wp-block-columns"><!-- wp:column {"smartlingLockId":"leftcolumn"} -->
<div class="wp-block-column"><!-- wp:paragraph {"someAttribute":"large","smartlingLockId":"leftparagraph"} -->
<p class="has-large-font-size">a left paragraph</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column {"smartlingLockId":"rightcolumn"} -->
<div class="wp-block-column"><!-- wp:paragraph {"someAttribute":"large","smartlingLockId":"rightparagraph"} -->
<p>a right paragraph</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:html {"someAttribute":"large","otherAttribute":"otherValue","smartlingLockId":"rootunlocked"} -->
<h1>:)</h1>
<!-- /wp:html -->

<!-- wp:paragraph {"someAttribute":"large","smartlingLockId":"rootlocked"} -->
<p class="has-large-font-size">Root level paragraph</p>
<!-- /wp:paragraph -->
HTML);
        $submission = $this->uploadDownload($this->submissionManager->storeEntity(
            $this->translationHelper->prepareSubmission('post', $this->sourceBlogId, $postId, $this->targetBlogId)),
        );

        $this->siteHelper->withBlog($this->targetBlogId, function () use ($submission) {
            $post = get_post($submission->getTargetId());
            $this->assertInstanceOf(\WP_Post::class, $post);
            $count = 0;
            $replaced = str_replace([
                '{"someAttribute":"[l~árgé]","smartlingLockId":"leftparagraph"}',
                '{"someAttribute":"[l~árgé]","smartlingLockId":"rightparagraph"}',
                '{"someAttribute":"[l~árgé]","otherAttribute":"[ó~thé~rVá~lúé]","smartlingLockId":"rootunlocked"}',
                '{"someAttribute":"[l~árgé]","smartlingLockId":"rootlocked"}',
            ], [
                '{"someAttribute":"large","smartlingLockId":"leftparagraph","smartlingLockedAttributes":"someAttribute"}',
                '{"someAttribute":"large","smartlingLockId":"rightparagraph"}',
                '{"someAttribute":"large","otherAttribute":"otherValue","smartlingLockId":"rootunlocked"}',
                '{"someAttribute":"large","smartlingLockId":"rootlocked","smartlingLockedAttributes":"someAttribute"}',
            ], $post->post_content, $count);
            $this->assertEquals(4, $count, 'Expected 4 replacements in ' . $post->post_content);
            $post->post_content = $replaced;
            $result = wp_update_post($post);
            $this->assertEquals($result, $submission->getTargetId());
        });

        $this->forceSubmissionDownload($submission);
        $this->assertEquals(<<<HTML
<!-- wp:columns {"smartlingLockId":"columns"} -->
<div class="wp-block-columns"><!-- wp:column {"smartlingLockId":"leftcolumn"} -->
<div class="wp-block-column"><!-- wp:paragraph {"someAttribute":"large","smartlingLockId":"leftparagraph","smartlingLockedAttributes":"someAttribute"} -->
<p class="has-large-font-size">[á ~léf~t pár~ágr~áph]</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column {"smartlingLockId":"rightcolumn"} -->
<div class="wp-block-column"><!-- wp:paragraph {"someAttribute":"[l~árgé]","smartlingLockId":"rightparagraph"} -->
<p>[á ~ríg~ht pá~rágr~áph]</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:html {"someAttribute":"[l~árgé]","otherAttribute":"[ó~thé~rVá~lúé]","smartlingLockId":"rootunlocked"} -->
<h1>[:~)]</h1>
<!-- /wp:html -->

<!-- wp:paragraph {"someAttribute":"large","smartlingLockId":"rootlocked","smartlingLockedAttributes":"someAttribute"} -->
<p class="has-large-font-size">[R~óót ~lévé~l pá~rágr~áph]</p>
<!-- /wp:paragraph -->
HTML
, $this->getTargetPost($this->siteHelper, $submission)->post_content);
    }

    public function testInnerBlocks()
    {
        $content = <<<HTML
<!-- wp:sf/fourup-blade-layout-one {"backgroundMediaId":57,"backgroundMediaUrl":"base-url/en-us/wp-content/uploads/sites/4/2021/04/Bridge.jpg","accentMediaId":21,"accentMediaUrl":"base-url/en-us/wp-content/uploads/sites/4/2021/04/Highway.jpg","accentMobileMediaId":21,"accentMobileMediaUrl":"base-url/en-us/wp-content/uploads/sites/4/2021/04/Highway.jpg","backgroundMobileMediaId":57,"backgroundMobileMediaUrl":"base-url/en-us/wp-content/uploads/sites/4/2021/04/Bridge.jpg"} -->
<div class="wp-block-sf-fourup-blade-layout-one"><!-- wp:sf/post {"id":"bc321c81-f35a-475b-aeef-d0ea1183864a"} /-->

<!-- wp:sf/post {"id":1} /-->

<!-- wp:sf/post {"id":2} /-->

<!-- wp:sf/post {"id":3} /--></div>
<!-- /wp:sf/fourup-blade-layout-one -->
<p>Not a Gutenberg block</p>
HTML;
        $postIds = [];
        $submissions = [];
        for ($id = 1; $id < 4; $id++) {
            $postIds[] = $this->createPost('post', "title $id", "Post $id content");
        }
        $postIds[] = $this->createPost('post', 'main title', $content);

        // manual ordering to force ids change on target site
        $submissions[] = $this->translationHelper->prepareSubmission('post', $this->sourceBlogId, $postIds[1], $this->targetBlogId);
        $submissions[] = $this->translationHelper->prepareSubmission('post', $this->sourceBlogId, $postIds[2], $this->targetBlogId);
        $submissions[] = $this->translationHelper->prepareSubmission('post', $this->sourceBlogId, $postIds[0], $this->targetBlogId);
        $submissions[] = $this->translationHelper->prepareSubmission('post', $this->sourceBlogId, $postIds[3], $this->targetBlogId);

        foreach ($submissions as $submission) {
            $submission = $this->submissionManager->storeEntity($submission);
            $this->addToUploadQueue($submission->getId());
        }
        $this->withBlockRules($this->rulesManager, ['test' => [
            'block' => 'sf/post',
            'path' => 'id',
            'replacerId' => 'related|post',
        ]], function () use ($submissions) {
            $this->executeUpload();
            $this->forceSubmissionDownload($submissions[3]);
        });

        foreach ($submissions as &$submission) {
            $submission = $this->translationHelper->reloadSubmission($submission);
        }
        unset($submission);
        $submission = ArrayHelper::first($this->submissionManager->find(['id' => $submissions[3]->getId()]));

        $blocks = $this->getGutenbergBlockHelper()->parseBlocks($this->getTargetPost($this->siteHelper, $submission)->post_content);
        $this->assertCount(2, $blocks, 'Expected to have an wp:sf/fourup-blade-layout-one block, and non-Gutenberg block');
        $innerBlocks = $blocks[0]->getInnerBlocks();
        $this->assertCount(4, $innerBlocks);
        $this->assertEquals('[b~c321~c81-~f35á~-475~b-áé~éf-d~0éá1~1838~64á]', $innerBlocks[0]->getAttributes()['id'], 'Expected non-numeric id property to be translated');
        for ($id = 1; $id < 4; $id++) {
            $this->assertEquals(ArrayHelper::first(array_filter($submissions, static function ($submission) use ($id) {
                return $submission->getSourceId() === $id;
            }))->getTargetId(), $innerBlocks[$id]->getAttributes()['id'], 'Expected id to equal target id');
        }
        $this->assertEquals("\n<p>[Ñ~ót á ~Gúté~ñbé~rg bl~óck]</p>", $blocks[1]->getInnerHtml(), 'Expected non-Gutenberg block to be translated');
    }

    public function testCopyAndExclude()
    {
        $content = <<<HTML
<!-- wp:si/block {"otherAttribute":"otherValue","copyAttribute":"copyValue","excludeAttribute":"excludeValue"} -->
<!-- wp:si/nested {"copyAttribute":"ca2"} -->
<p>Nested 1 content</p>
<!-- /wp:si/nested -->
<!-- wp:si/nested {"excludeAttribute":"ca3"} -->
<p>Nested 2 content</p>
<!-- /wp:si/nested -->
<!-- /wp:si/block -->
HTML;
        $expected = <<<HTML
<!-- wp:si/block {"otherAttribute":"[ó~thé~rVá~lúé]","copyAttribute":"copyValue","excludeAttribute":null} -->
<!-- wp:si/nested {"copyAttribute":"[c~á2]"} -->
<p>[Ñ~ést~éd 1 c~óñt~éñt]</p>
<!-- /wp:si/nested -->
<!-- wp:si/nested {"excludeAttribute":"[c~á3]"} -->
<p>[Ñ~ést~éd 2 c~óñt~éñt]</p>
<!-- /wp:si/nested -->
<!-- /wp:si/block -->
HTML;
        $postId = $this->createPost('post', 'main title', $content);
        $submission = $this->translationHelper->prepareSubmission('post', $this->sourceBlogId, $postId, $this->targetBlogId);
        $submission = $this->submissionManager->storeEntity($submission);
        $this->addToUploadQueue($submission->getId());
        $this->withBlockRules($this->rulesManager, [
            'copy' => [
                'block' => 'si/block',
                'path' => 'copyAttribute',
                'replacerId' => 'copy',
            ],
            'exclude' => [
                'block' => 'si/block',
                'path' => 'excludeAttribute',
                'replacerId' => 'exclude',
            ],
        ], function () use ($submission) {
            $this->executeUpload();
            $this->forceSubmissionDownload($submission);
        });

        $submission = $this->translationHelper->reloadSubmission($submission);

        $this->assertEquals($expected, $this->getTargetPost($this->siteHelper, $submission)->post_content);
    }

    public function testReplacerJsonPath()
    {
        $attachmentSourceId = $this->createAttachment();
        $content = <<<HTML
<!-- wp:si/test {"panelPromo": { "eyebrowMediaImage": {"id": $attachmentSourceId} }} /-->
HTML;
        $postId = $this->createPost('post', 'JSON path translation', $content);
        $attachment = $this->translationHelper->prepareSubmission('attachment', $this->sourceBlogId, $attachmentSourceId, $this->targetBlogId);
        $post = $this->translationHelper->prepareSubmission('post', $this->sourceBlogId, $postId, $this->targetBlogId);
        $submissions = [$attachment, $post];
        foreach ($submissions as $submission) {
            $submission = $this->submissionManager->storeEntity($submission);
            $this->addToUploadQueue($submission->getId());
        }
        $this->withBlockRules($this->rulesManager, ['test' => [
            'block' => 'si/test',
            'path' => '$.panelPromo.eyebrowMediaImage.id',
            'replacerId' => 'related|post',
        ]], function () use ($submissions) {
            $this->executeUpload();
            $this->forceSubmissionDownload($submissions[0]);
            $this->forceSubmissionDownload($submissions[1]);
        });
        foreach ($submissions as &$submission) {
            $submission = $this->translationHelper->reloadSubmission($submission);
        }
        unset($submission);
        $attachmentTargetId = $submissions[0]->getTargetId();
        $expectedContent = <<<HTML
<!-- wp:si/test {"panelPromo":{"eyebrowMediaImage":{"id":$attachmentTargetId}}} /-->
HTML;
        $this->assertNotEquals($attachmentSourceId, $attachmentTargetId);
        $this->assertEquals($expectedContent, $this->getTargetPost($this->siteHelper, $submissions[1])->post_content);
    }

    public function testReplacerNestedJsonPath()
    {
        $attachmentIds = [];
        $attachmentIdPairs = [];
        $attachments = [];
        while (count($attachmentIds) < 4) {
            $attachmentIds[] = $this->createAttachment();
        }
        $postId = $this->createPost('post', 'JSON path translation', sprintf(file_get_contents(DIR_TESTDATA . '/wp-745-source.html'), ...$attachmentIds));
        foreach ($attachmentIds as $attachmentId) {
            $attachments[] = $this->translationHelper->prepareSubmission('attachment', $this->sourceBlogId, $attachmentId, $this->targetBlogId);
        }
        $post = $this->translationHelper->prepareSubmission('post', $this->sourceBlogId, $postId, $this->targetBlogId);
        $submissions = array_merge($attachments, [$post]);
        foreach ($submissions as $submission) {
            $submission = $this->submissionManager->storeEntity($submission);
            $this->addToUploadQueue($submission->getId());
        }
        $this->withBlockRules($this->rulesManager, [
            'teste' => [
                'block' => '.+',
                'path' => '$.eyebrow.image.id',
                'replacerId' => 'related|post',
            ],
            'testb' => [
                'block' => '.+',
                'path' => '$.blade_background_type.blade_background_image.id',
                'replacerId' => 'related|post',
            ],
            'testm' => [
                'block' => '.+',
                'path' => '$.media.image.id',
                'replacerId' => 'related|post',
            ],
        ], function () use ($submissions) {
            $this->executeUpload();
            foreach ($submissions as $submission) {
                $this->forceSubmissionDownload($submission);
            }
        });
        foreach ($submissions as &$submission) {
            $submission = $this->translationHelper->reloadSubmission($submission);
            if (in_array($submission->getSourceId(), $attachmentIds, true)) {
                $attachmentIdPairs[$submission->getSourceId()] = $submission->getTargetId();
            }
            if (count($attachmentIdPairs) === 4) {
                $attachmentIdPairs[] = $submission->getSourceId(); // The same id is used, but no rule set up, so the id is expected to be copied
            }
        }
        unset($submission);
        foreach ($attachmentIdPairs as $sourceId => $targetId) {
            $this->assertNotEquals($sourceId, $targetId, "Expected attachment sourceId $sourceId to change");
        }
        $this->assertEquals(
            sprintf(file_get_contents(DIR_TESTDATA . '/wp-745-expected.html'), ...$attachmentIdPairs),
            $this->getTargetPost($this->siteHelper, $submissions[count($attachmentIds)])->post_content,
            "All strings in block attributes should be converted to pseudo translated strings,\n" .
            "all boolean values and digits that are not submission ids with matching rules should be preserved,\n" .
            "source submission ids with matching rules should be replaced with target submission ids",
        );
    }

    public function testCoreImageClassTranslation()
    {
        $attachmentSourceId = $this->createAttachment();
        $content = <<<HTML
<!-- wp:image {"id":$attachmentSourceId,"sizeSlug":"large"} -->
<figure class="wp-block-image size-large"><img src="http://example.com/wp-content/uploads/2021/11/imageClass.png" alt="" class="wp-image-$attachmentSourceId"/></figure>
<!-- /wp:image -->
<!-- wp:image {"id":$attachmentSourceId,"sizeSlug":"large"} -->
<figure class="wp-block-image size-large"><img class="wp-image-$attachmentSourceId" src="http://example.com/wp-content/uploads/2021/11/imageClass.png" alt="" /></figure>
<!-- /wp:image -->
<!-- wp:image {"id":$attachmentSourceId,"sizeSlug":"large"} -->
<figure class="wp-block-image size-large"><img src="http://example.com/wp-content/uploads/2021/11/imageClass.png" alt="" class="irrelevant wp-image-$attachmentSourceId someOtherClass"/></figure>
<!-- /wp:image -->
HTML;
        $postId = $this->createPost('post', "Image Class Translation", $content);
        $attachment = $this->translationHelper->prepareSubmission('attachment', $this->sourceBlogId, $attachmentSourceId, $this->targetBlogId);
        $post = $this->translationHelper->prepareSubmission('post', $this->sourceBlogId, $postId, $this->targetBlogId);
        $submissions = [$attachment, $post];
        foreach ($submissions as $submission) {
            assert($submission instanceof SubmissionEntity);
            $this->submissionManager->storeEntity($submission);
            $this->addToUploadQueue($submission->getId());
        }
        $this->executeUpload();
        $this->forceSubmissionDownload($submissions[0]);
        $this->forceSubmissionDownload($submissions[1]);
        foreach ($submissions as &$submission) {
            $submission = $this->translationHelper->reloadSubmission($submission);
        }
        unset($submission);
        $attachmentTargetId = $submissions[0]->getTargetId();
        $expectedContent = <<<HTML
<!-- wp:image {"id":$attachmentTargetId,"sizeSlug":"[l~árgé]"} -->
<figure class="wp-block-image size-large"><img src="http://example.com/wp-content/uploads/2021/11/imageClass.png" alt="" class="wp-image-$attachmentTargetId" /></figure>
<!-- /wp:image -->
<!-- wp:image {"id":$attachmentTargetId,"sizeSlug":"[l~árgé]"} -->
<figure class="wp-block-image size-large"><img class="wp-image-$attachmentTargetId" src="http://example.com/wp-content/uploads/2021/11/imageClass.png" alt="" /></figure>
<!-- /wp:image -->
<!-- wp:image {"id":$attachmentTargetId,"sizeSlug":"[l~árgé]"} -->
<figure class="wp-block-image size-large"><img src="http://example.com/wp-content/uploads/2021/11/imageClass.png" alt="" class="irrelevant wp-image-$attachmentTargetId someOtherClass" /></figure>
<!-- /wp:image -->
HTML;
        $this->assertNotEquals($attachmentSourceId, $attachmentTargetId);
        $this->assertEquals($expectedContent, $this->getTargetPost($this->siteHelper, $submissions[1])->post_content);
    }
}
