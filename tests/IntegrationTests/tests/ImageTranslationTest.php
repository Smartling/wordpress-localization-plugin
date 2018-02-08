<?php

namespace Smartling\Tests\IntegrationTests\tests;

use Smartling\Tests\IntegrationTests\SmartlingUnitTestCaseAbstract;

/**
 * Class ImageTranslationTest
 * @package Smartling\Tests\IntegrationTests\tests
 */
class ImageTranslationTest extends SmartlingUnitTestCaseAbstract
{
    public function testImageTranslation()
    {
        $submissionManager = $this->getSubmissionManager();
        $attachmentId = $this->createAttachment();
        $submission = $this->createSubmission('attachment', $attachmentId);
        $submissionId = $submissionManager->storeEntity($submission)->getId();
        $this->executeUpload();
        $submission = $this->getSubmissionById($submissionId);
        $attachment = (array)$this->factory()->attachment->get_object_by_id($attachmentId);
        $guid = $attachment['guid'];
        $filename = str_replace('http://' . getenv('WP_INSTALLATION_DOMAIN'), '', $guid);
        self::assertTrue(file_exists($filename));
        $targetFileName = str_replace('uploads', 'uploads/sites/' . $submission->getTargetBlogId(), $filename);
        self::assertTrue(file_exists($targetFileName));
        $sourcehash = md5(file_get_contents($filename));
        $targethash = md5(file_get_contents($targetFileName));
        self::assertTrue($sourcehash === $targethash);
    }
}
