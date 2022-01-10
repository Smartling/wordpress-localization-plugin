<?php

namespace IntegrationTests\tests;

use Smartling\Helpers\ArrayHelper;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Tests\IntegrationTests\SmartlingUnitTestCaseAbstract;

class AdvancedCustomFieldsTest extends SmartlingUnitTestCaseAbstract
{
    public static function setUpBeforeClass(): void
    {
        self::wpCliExec('plugin', 'activate', 'acf-pro-test-definitions --network');
    }

    private function getMetadata($taxonomyId, $imageId = 0)
    {
        return [
            'text_field'                                                    => 'Clone Text Field',
            '_text_field'                                                   => 'field_5ad0641c763ad',
            'number_field'                                                  => '123',
            '_number_field'                                                 => 'field_5ad06431763ae',
            'e-mail_field'                                                  => 'clone@email.field',
            '_e-mail_field'                                                 => 'field_5ad06445763af',
            'password_field'                                                => 'Clone Password Field',
            '_password_field'                                               => 'field_5ad06463763b0',
            'html_field'                                                    => 'Html Sample',
            '_html_field'                                                   => 'field_5ad06473763b1',
            'image_field'                                                   => $imageId,
            '_image_field'                                                  => 'field_5ad06485763b2',
            'select_field'                                                  => 'red',
            '_select_field'                                                 => 'field_5ad06494763b3',
            'checkbox_field'                                                => ['yellow'],
            '_checkbox_field'                                               => 'field_5ad064c6763b4',
            'logical_field'                                                 => '1',
            '_logical_field'                                                => 'field_5ad064e6763b5',
            'page_link_field'                                               => $imageId,
            '_page_link_field'                                              => 'field_5ad064fb763b6',
            'post_object_field'                                             => $imageId,
            '_post_object_field'                                            => 'field_5ad06541763b7',
            'taxonomy_field'                                                => $taxonomyId,
            '_taxonomy_field'                                               => 'field_5ad0655e763b8',
            'repeater_field_0_repeater_text_field'                          => 'Smale Text',
            '_repeater_field_0_repeater_text_field'                         => 'field_5ad0759209c41',
            'repeater_field_0_repeater_image_field'                         => $imageId,
            '_repeater_field_0_repeater_image_field'                        => 'field_5ad0759609c42',
            'repeater_field'                                                => '1',
            '_repeater_field'                                               => 'field_5ad0757a09c40',
            'clone_field'                                                   => ' ',
            '_clone_field'                                                  => 'field_5ad075cc18849',
            'flexible_content_field'                                        => ['flexible_layout_1',
                                                                                'flexible_layout_1'],
            '_flexible_content_field'                                       => 'field_5ad075ef1884a',
            'flexible_content_field_0_flexible_content_text_field'          => 'Some content',
            '_flexible_content_field_0_flexible_content_text_field'         => 'field_5ad0760f1884b',
            'flexible_content_field_0_flexible_content_layout_image_field'  => $imageId,
            '_flexible_content_field_0_flexible_content_layout_image_field' => 'field_5ad076261884c',
            'flexible_content_field_1_flexible_content_text_field'          => 'Some content 2',
            '_flexible_content_field_1_flexible_content_text_field'         => 'field_5ad0760f1884b',
            'flexible_content_field_1_flexible_content_layout_image_field'  => $imageId,
            '_flexible_content_field_1_flexible_content_layout_image_field' => 'field_5ad076261884c',
        ];
    }

    private function createSourcePostWithMetadata($imageId, $taxonomy)
    {
        return $this->createPostWithMeta('title', 'body', 'post', $this->getMetadata($taxonomy, $imageId));
    }

    private function createTaxonomy($taxonomyName = 'Some taxonomy', $taxonomy = 'category')
    {
        $_taxonomy = wp_insert_term($taxonomyName, $taxonomy);

        return $_taxonomy['term_id'];
    }

    public function testAdvancedCustomFields()
    {
        $imageId = $this->createAttachment();
        $taxonomyIds = $this->createTaxonomy('Category A');
        $postId = $this->createSourcePostWithMetadata($imageId, $taxonomyIds);
        $translationHelper = $this->getTranslationHelper();

        $submission = $translationHelper->prepareSubmission('post', 1, $postId, 2, true);

        /**
         * Check submission status
         */
        $this->assertEquals(SubmissionEntity::SUBMISSION_STATUS_NEW, $submission->getStatus());
        $this->assertEquals(1, $submission->getIsCloned());

        $this->executeUpload();

        $submissions = $this->getSubmissionManager()->find(
            [
                'content_type' => 'attachment',
                'is_cloned'    => 1,
            ]);

        $this->assertCount(1, $submissions);

        $attachmentSubmission = ArrayHelper::first($submissions);

        $submissions = $this->getSubmissionManager()->find(
            [
                'content_type' => 'category',
                'is_cloned'    => 1,
            ]);

        $this->assertCount(1, $submissions);

        $categorySubmission = ArrayHelper::first($submissions);

        $expectedMetadata = $this->getMetadata($categorySubmission->getTargetId(), $attachmentSubmission->getTargetId());

        $submissions = $this->getSubmissionManager()->find(
            [
                'content_type' => 'post',
                'is_cloned'    => 1,
            ]);

        $this->assertCount(1, $submissions);

        $postSubmission = ArrayHelper::first($submissions);

        $realMetadata = $this->getContentHelper()->readTargetMetadata($postSubmission);

        foreach ($realMetadata as & $realMetadatum) {
            $realMetadatum = maybe_unserialize($realMetadatum);
        }

        foreach ($expectedMetadata as $eKey => $eValue) {
            self::assertArrayHasKey($eKey, $realMetadata);

            if (is_array($eValue)) {
                $eValue = array_map(function ($e) {
                    return (string)$e;
                }, $eValue);
            } else {
                $eValue = (string)$eValue;
            }
            self::assertEquals(
                $realMetadata[$eKey], $eValue, vsprintf('Expected: %s=>%s, real %s=>%s',
                         [
                             $eKey,
                             var_export($eValue, true),
                             $eKey,
                             var_export($realMetadata[$eKey], true)
                         ]
                )
            );
        }
    }

    public function testAcfGutenbergRelatedIdTranslation() {
        $submissionManager = $this->getSubmissionManager();
        $translationHelper = $this->getTranslationHelper();
        $acfImageFieldId = 'field_5ad06485763b2'; // from tests/IntegrationTests/testdata/acf-pro-test-definitions/acf-pro-test-definitions.php
        $sourceBlogId = 1;
        $targetBlogId = 2;

        $this->createAttachment(); // Unrelated attachment, purposed to increment the tested attachment id
        $imageId = $this->createAttachment();

        $postId = $this->createPost(
            'post',
            'title',
            sprintf(
                '<!-- wp:acf/custom-image {"id":"","name":"acf/custom-image","data":{"mediaId":%d,"_mediaId":"%s"}} /-->',
                $imageId,
                $acfImageFieldId,
            ),
        );
        $submission = $translationHelper->prepareSubmission('post', $sourceBlogId, $postId, $targetBlogId);
        $submission->getFileUri();
        $submission = $submissionManager->storeEntity($submission);
        $this->uploadDownload($submission);
        $attachmentSubmission = ArrayHelper::first($submissionManager->find([SubmissionEntity::FIELD_SOURCE_ID => $imageId]));
        $this->assertInstanceOf(SubmissionEntity::class, $attachmentSubmission);
        $submission = ArrayHelper::first($submissionManager->find([SubmissionEntity::FIELD_CONTENT_TYPE => 'post']));
        $this->assertInstanceOf(SubmissionEntity::class, $submission);
        $targetPost = $this->getTargetPost($this->getSiteHelper(), $submission);
        $this->assertNotEquals($imageId, $attachmentSubmission->getTargetId(), 'Attachment id expected to change after translation');
        $this->assertEquals(
            sprintf(
            '<!-- wp:acf/custom-image %s /-->',
                stripslashes(json_encode([
                    'id' => '',
                    'name' => '[á~cf/c~ústó~m-ím~ágé]',
                    'data' => ['mediaId' => (string)$attachmentSubmission->getTargetId(), '_mediaId' => $acfImageFieldId],
                ], JSON_UNESCAPED_UNICODE)),
            ),
            $targetPost->post_content,
        );
    }
}
