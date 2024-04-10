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
        $attachmentId = $this->createAttachment();
        $submission = $this->uploadDownload($this->createSubmission('attachment', $attachmentId));
        $attachment = (array)$this->factory()->attachment->get_object_by_id($attachmentId);
        $filename = str_replace('http://' . getenv('WP_INSTALLATION_DOMAIN'), '', $attachment['guid']);
        self::assertFileExists($filename);
        $targetFileName = str_replace('uploads', 'uploads/sites/' . $submission->getTargetBlogId(), $filename);
        self::assertFileExists($targetFileName);
        self::assertSame(md5(file_get_contents($filename)), md5(file_get_contents($targetFileName)));
    }
}
