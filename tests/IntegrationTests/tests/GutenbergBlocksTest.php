<?php

namespace IntegrationTests\tests;

use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\SiteHelper;
use Smartling\Helpers\TranslationHelper;
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
        $this->rulesManager->offsetSet('blade', [
            'block' => 'sf/post',
            'path' => 'id',
            'replacerId' => 'related|post',
        ]);
        $this->rulesManager->saveData();
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
            $submission->getFileUri();
            $this->submissionManager->storeEntity($submission);
        }
        $this->executeUpload();
        $this->forceSubmissionDownload($submissions[3]);
        foreach ($submissions as &$submission) {
            $submission = $this->translationHelper->reloadSubmission($submission);
        }
        unset($submission);
        $submission = ArrayHelper::first($this->submissionManager->find(['id' => $submissions[3]->getId()]));

        // cleanup
        $this->rulesManager->offsetUnset('blade');
        $this->rulesManager->saveData();

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
}
