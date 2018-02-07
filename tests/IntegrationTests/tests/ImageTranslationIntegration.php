<?php

namespace Smartling\Tests\IntegrationTests\tests;

use Smartling\Bootstrap;
use Smartling\ContentTypes\CustomPostType;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\TranslationHelper;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Settings\Locale;
use Smartling\Settings\SettingsManager;
use Smartling\Settings\TargetLocale;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;
use Smartling\Tests\IntegrationTests\SmartlingUnitTestCaseAbstract;

class ImageTranslationIntegration extends SmartlingUnitTestCaseAbstract
{

    protected $backupStaticAttributesBlacklist = [
        __CLASS__ => [
            'imageId',
            'submissionId',
        ],
    ];

    private static $imageId = 0;

    private static $submissionId = 0;

    public function testCreateProfile()
    {
        $profile = $this->createProfile();
        $manager = $this->getSettingsManager();
        $this->profile = $manager->storeEntity($profile);
        /**
         * Check that profile is created
         */
        $this->assertTrue(1 === $this->profile->getId());
    }

    /**
     * @depends testCreateProfile
     */
    public function testCreateSubmission()
    {
        self::$imageId = $this->createAttachment();

        $translationHelper = $this->getTranslationHelper();

        /**
         * @var SubmissionEntity $submission
         */
        $submission = $translationHelper->prepareSubmission('attachment', 1, self::$imageId, 2);
        self::$submissionId = $submission->getId();
        /**
         * Check submission status
         */
        $this->assertTrue(1 === self::$submissionId);
        $this->assertTrue(SubmissionEntity::SUBMISSION_STATUS_NEW === $submission->getStatus());

    }

    public function testImageTranslation()
    {
        $this->executeUpload();
        $submissionManager = $this->getSubmissionManager();
        $result = $submissionManager->getEntityById(self::$submissionId);
        $submission = ArrayHelper::first($result);
        /**
         * @var SubmissionEntity $submission
         */
        $this->assertTrue(SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS === $submission->getStatus());
        $attachment = (array)$this->factory()->attachment->get_object_by_id(self::$imageId);
        $guid = $attachment['guid'];
        $filename = str_replace('http://' . getenv('WP_INSTALLATION_DOMAIN'), '', $guid);
        $this->assertTrue(file_exists($filename));
        $targetFileName = str_replace('uploads', 'uploads/sites/' . $submission->getTargetBlogId(), $filename);
        $this->assertTrue(file_exists($targetFileName));
        $sourcehash = md5(file_get_contents($filename));
        $targethash = md5(file_get_contents($targetFileName));
        $this->assertTrue($sourcehash === $targethash);

    }

}