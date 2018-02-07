<?php

namespace Smartling\Tests\IntegrationTests\tests;

use Smartling\ContentTypes\CustomPostType;
use Smartling\Helpers\ArrayHelper;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Tests\IntegrationTests\SmartlingUnitTestCaseAbstract;

class MetadataPartialVsFullIntegration extends SmartlingUnitTestCaseAbstract
{
    protected $backupStaticAttributesBlacklist = [
        __CLASS__ => [
            'meta',
            'postId',
            'submissionId',
        ],
    ];

    private function createPostWithMeta($title, $body, $post_type = 'post', array $meta)
    {
        $template = [
            'post_title'   => $title,
            'post_content' => $body,
            'post_status'  => 'publish',
            'post_type'    => $post_type,
            'meta_input'   => $meta,
        ];

        $postId = $this->factory()->post->create_object($template);

        return $postId;
    }

    private function getProfile()
    {
        $profile = $this->getProfileById(1);

        if (false === $profile) {
            echo PHP_EOL . "No profile found. Creating..." . PHP_EOL;
            $profile = $this->createProfile();
            $this->getSettingsManager()->storeEntity($profile);
            $profile = $this->getProfileById(1);
        }

        return $profile;
    }

    private function updateMetaRebuildOnDownload($newValue)
    {
        $profile = $this->getProfile();
        /**
         * @var ConfigurationProfileEntity $profile
         */
        $profile->setCleanMetadataOnDownload($newValue);
        //print_r($profile->toArray(false));
        $profile = $this->getSettingsManager()->storeEntity($profile);
    }

    private static $postId = 0;

    private static $submissionId = 0;

    private static $meta = [
        'standard' => [
            'meta_a' => 'Meta Value A',
            'meta_b' => 'Meta Value B',
        ],
        'updated'  => [
            'meta_c' => 'Meta Value C',
            'meta_d' => 'Meta Value D',
        ],
    ];


    public function testTranslatePostWithMetadata()
    {
        $this->getLogger()->critical('**********');

        self::$postId = $this->createPostWithMeta('Title', 'Body', 'post', self::$meta['standard']);
        $submission = $this->createSubmission('post', self::$postId);
        self::$submissionId = $submission->getId();
        $this->updateMetaRebuildOnDownload(0);

        $originalMetaRead = $this->getContentHelper()->readSourceMetadata($submission);

        foreach (self::$meta['standard'] as $k => $v) {
            $this->assertArrayHasKey($k, $originalMetaRead);
            $this->assertEquals($originalMetaRead[$k], self::$meta['standard'][$k]);
        }
        $this->executeUpload();
        $this->forceSubmissionDownload($this->getSubmissionById(self::$submissionId));
        $submission = ArrayHelper::first($this->getSubmissionManager()->getEntityById(self::$submissionId));

        $metaRead = $this->getContentHelper()->readTargetMetadata($submission);
        foreach (self::$meta['standard'] as $k => $v) {
            $this->assertArrayHasKey($k, $metaRead);
        }
        $this->getLogger()->critical('**********!');
    }

    /**
     * @depends testTranslatePostWithMetadata
     */
    private function incomplete_testMetadata()
    {
        $this->getLogger()->critical('**********');
        foreach (self::$meta['standard'] as $k => $v) {
            delete_post_meta(self::$postId, $k);
        }
        foreach (self::$meta['updated'] as $k => $v) {
            add_post_meta(self::$postId, $k, $v, true);
        }
        $submission = $this->getSubmissionById(self::$submissionId);
        $originalMetaRead = $this->getContentHelper()->readSourceMetadata($submission);
        foreach (self::$meta['updated'] as $k => $v) {
            $this->assertArrayHasKey($k, $originalMetaRead);
            $this->assertEquals($originalMetaRead[$k], self::$meta['standard'][$k]);
        }
        $this->getLogger()->critical('**********');
        $this->updateMetaRebuildOnDownload(1);
        $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_NEW);
        $submission = $this->getSubmissionManager()->storeEntity($submission);
        $this->executeUpload();
        $this->forceSubmissionDownload($submission);
        $translationMetadata = $originalMetaRead = $this->getContentHelper()->readTargetMetadata($submission);
        foreach (self::$meta['updated'] as $k => $v) {
            $this->assertArrayHasKey($k, $translationMetadata);
        }
    }
}