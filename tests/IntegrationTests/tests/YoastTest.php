<?php

namespace IntegrationTests\tests;

use Smartling\Submissions\SubmissionEntity;
use Smartling\Tests\IntegrationTests\SmartlingUnitTestCaseAbstract;

class YoastTest extends SmartlingUnitTestCaseAbstract {
    public function testYoast(): void
    {
        $contentType = 'post';
        $synonyms = '["","","",""]';
        $postId = $this->createPostWithMeta('Yoast automated test post', '', $contentType, [
            '_yoast_wpseo_focuskeywords' => addslashes('[{"keyword":"Ryan Test","score":33},{"keyword":"TEST 2 JARON","score":33},{"keyword":"FRIDAY","score":47}]'),
            '_yoast_wpseo_keywordsynonyms' => addslashes($synonyms),
        ]);
        $sourceBlogId = 1;
        $targetBlogId = 2;
        $submission = $this->getTranslationHelper()->prepareSubmission($contentType, $sourceBlogId, $postId, $targetBlogId);

        $result = $this->uploadDownload($submission);
        $meta = $this->getContentHelper()->readTargetMetadata($result);
        $this->assertEquals($synonyms, $meta['_yoast_wpseo_keywordsynonyms']);
        $this->assertEquals('[{"keyword":"[R~\u00fd\u00e1\u00f1 T~\u00e9st]","score":33},{"keyword":"[T~\u00c9ST ~2 J\u00c1~R\u00d3\u00d1]","score":33},{"keyword":"[F~R\u00cdD~\u00c1\u00dd]","score":47}]', $meta['_yoast_wpseo_focuskeywords']);
    }
}
