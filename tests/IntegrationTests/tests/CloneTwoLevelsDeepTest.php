<?php

namespace IntegrationTests\tests;

use Smartling\DbAl\WordpressContentEntities\TaxonomyEntityStd;
use Smartling\Replacers\ReplacerFactory;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Tests\IntegrationTests\SmartlingUnitTestCaseAbstract;
use Smartling\Tuner\MediaAttachmentRulesManager;

class CloneTwoLevelsDeepTest extends SmartlingUnitTestCaseAbstract
{
    public function testCloneTwoLevelsDeep()
    {
        $childPostId = $this->createPost();

        $imageId = $this->createAttachment();
        set_post_thumbnail($childPostId, $imageId);
        $this->assertTrue(has_post_thumbnail($childPostId));

        $rootPostId = $this->createPost('post', 'root post', "<!-- wp:test/tld {\"id\":$childPostId} /-->");

        $translationHelper = $this->getTranslationHelper();
        $man = $this->getRulesManager();
        $man->offsetSet('test', [
            'block' => 'test/tld',
            'path' => 'id',
            'replacerId' => ReplacerFactory::RELATED_POSTBASED,
        ]);
        $man->saveData();
        $rootSubmission = $translationHelper->prepareSubmission('post', 1, $rootPostId, 2, true);
        $childSubmission = $translationHelper->prepareSubmission('post', 1, $childPostId, 2, true);
        $translationHelper->prepareSubmission('post', 1, $imageId, 2, true);

        $rootSubmission = $this->uploadDownload($rootSubmission);
        $man->offsetUnset('test');
        $man->saveData();
        $childSubmission = $this->getSubmissionById($childSubmission->id);
        $this->assertEquals("<!-- wp:test/tld {\"id\":$childSubmission->target_id} /-->", $this->getTargetPost($this->getSiteHelper(), $rootSubmission)->post_content);
    }
}
