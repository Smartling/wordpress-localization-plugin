<?php

namespace Smartling\Tests\IntegrationTests\tests;

use Smartling\Submissions\SubmissionEntity;
use Smartling\Tests\IntegrationTests\SmartlingUnitTestCaseAbstract;

class MetadataPartialVsFullTest extends SmartlingUnitTestCaseAbstract
{

    public function testTranslatePostWithMetadata()
    {
        $metadata = [
            'standard' => [
                'meta_a' => 'Meta Value A',
                'meta_b' => 'Meta Value B',
            ],
            'updated'  => [
                'meta_c' => 'Meta Value C',
                'meta_d' => 'Meta Value D',
            ],
        ];

        $postId = $this->createPostWithMeta('Title', 'Body', 'post', $metadata['standard']);
        $submission = $this->createSubmission('post', $postId);
        $submission = $this->getSubmissionManager()->storeEntity($submission);

        $originalMetaRead = $this->getContentHelper()->readSourceMetadata($submission);

        /**
         * Check if test post has initial metadata
         */
        foreach ($metadata['standard'] as $k => $v) {
            self::assertArrayHasKey($k, $originalMetaRead);
            self::assertEquals($originalMetaRead[$k], $metadata['standard'][$k]);
        }

        $this->uploadDownload($submission);
        $submission = $this->getSubmissionById($submission->getId());

        $metaRead = $this->getContentHelper()->readTargetMetadata($submission);
        foreach ($metadata['standard'] as $k => $v) {
            self::assertArrayHasKey($k, $metaRead);
        }
        self::flush_cache();
        /**
         * Updating source metadata
         */
        foreach ($metadata['standard'] as $k => $v) {
            delete_post_meta($postId, $k);
        }
        foreach ($metadata['updated'] as $k => $v) {
            add_post_meta($postId, $k, $v, true);
        }
        self::flush_cache();

        $this->uploadDownload($submission);
        $submission = $this->getSubmissionById($submission->getId());

        $metaRead = $this->getContentHelper()->readTargetMetadata($submission);
        foreach (array_merge($metadata['standard'], $metadata['updated']) as $k => $v) {
            self::assertArrayHasKey($k, $metaRead);
        }
        self::flush_cache();

        $profile = $this->getProfileById(1);
        $profile->setCleanMetadataOnDownload(1);
        $this->getSettingsManager()->storeEntity($profile);

        $this->uploadDownload($submission);
        $submission = $this->getSubmissionById($submission->getId());

        $metaRead = $this->getContentHelper()->readTargetMetadata($submission);
        foreach ($metadata['updated'] as $k => $v) {
            self::assertArrayHasKey($k, $metaRead);
        }
    }
}
