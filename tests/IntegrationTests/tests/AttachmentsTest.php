<?php

namespace IntegrationTests\tests;

use Smartling\Helpers\SiteHelper;
use Smartling\Helpers\TranslationHelper;
use Smartling\Submissions\SubmissionManager;
use Smartling\Tests\IntegrationTests\SmartlingUnitTestCaseAbstract;

class AttachmentsTest extends SmartlingUnitTestCaseAbstract
{
    private SiteHelper $siteHelper;
    private SubmissionManager $submissionManager;
    private TranslationHelper $translationHelper;
    private int $sourceBlogId = 1;
    private int $targetBlogId = 2;

    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->siteHelper = $this->getSiteHelper();
        $this->submissionManager = $this->getSubmissionManager();
        $this->translationHelper = $this->getTranslationHelper();
    }

    public function testAnchors()
    {
        $postId = $this->createPost('post', "Anchor test post", '<a href="https://example.com">A link</a>');

        $submission = $this->translationHelper->prepareSubmission('post', $this->sourceBlogId, $postId, $this->targetBlogId);
        $submission->getFileUri();
        $submission = $this->submissionManager->storeEntity($submission);
        $submission = $this->uploadDownload($submission);

        $this->assertEquals('<a href="https://example.com">[Á ~líñk]</a>', $this->getTargetPost($this->siteHelper, $submission)->post_content);
    }
}
