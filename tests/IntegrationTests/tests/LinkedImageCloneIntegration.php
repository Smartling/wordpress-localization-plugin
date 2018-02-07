<?php

namespace Smartling\Tests\IntegrationTests\tests;

use Smartling\ContentTypes\CustomPostType;
use Smartling\Helpers\ArrayHelper;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Tests\IntegrationTests\SmartlingUnitTestCaseAbstract;

class LinkedImageCloneIntegration extends SmartlingUnitTestCaseAbstract
{
    protected $backupStaticAttributesBlacklist = [
        __CLASS__ => [
            'imageId',
            'postId',
            'imageSubmissionId',
        ],
    ];

    private static $imageId           = 0;
    private static $postId            = 0;
    private static $imageSubmissionId = 0;

    public function setUp()
    {
        parent::setUp();
    }

    public function testPageThumbnail()
    {
        self::$imageId = $this->createAttachment();
        self::$postId = $this->createPost('page');
        set_post_thumbnail(self::$postId, self::$imageId);
        $this->assertTrue(has_post_thumbnail(self::$postId));
    }

    public function testCloneImage()
    {
        $translationHelper = $this->getTranslationHelper();

        /**
         * @var SubmissionEntity $submission
         */
        $submission = $translationHelper->prepareSubmission('attachment', 1, self::$imageId, 2, true);
        self::$imageSubmissionId = $submission->getId();

        /**
         * Check submission status
         */
        $this->assertTrue(SubmissionEntity::SUBMISSION_STATUS_NEW === $submission->getStatus());
        $this->assertTrue(1 === $submission->getIsCloned());

        $this->executeUpload();

        $submissions = $this->getSubmissionManager()->find(
            [
                'content_type' => 'page',
                'is_cloned'    => 1,
            ]);
        $this->assertTrue(0 === count($submissions));

        $submissions = $this->getSubmissionManager()->find(
            [
                'content_type' => 'attachment',
                'is_cloned'    => 1,
            ]);
        $this->assertTrue(1 === count($submissions));
        $submission = ArrayHelper::first($submissions);
        /**
         * @var SubmissionEntity $submission
         */
        $this->assertTrue(self::$imageSubmissionId === $submission->getId());
    }
}