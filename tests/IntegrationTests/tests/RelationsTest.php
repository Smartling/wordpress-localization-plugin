<?php

namespace IntegrationTests\tests;

use JetBrains\PhpStorm\ArrayShape;
use Smartling\ContentTypes\ContentTypeHelper;
use Smartling\Helpers\ArrayHelper;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Tests\IntegrationTests\SmartlingUnitTestCaseAbstract;

class RelationsTest extends SmartlingUnitTestCaseAbstract
{
    private const ORIGINAL_BLOG_ID = 1;
    private const TRANSLATE_BLOG_ID = 2;
    private const CLONE_BLOG_ID = 3;

    public function testSubmitPostWithCategoryWhichHasParentCategory()
    {
        $this->markTestSkipped();
        $categoryId = $this->createTerm('New category');

        $postId = $this->createPost();

        $sourceBlogId = 1;
        $targetBlogId = 2;

        $this->addTaxonomyToPost($postId, $categoryId);

        $translationHelper = $this->getTranslationHelper();
        $category = $translationHelper->prepareSubmission('category', $sourceBlogId, $categoryId, $targetBlogId);
        $submission = $translationHelper->prepareSubmission(ContentTypeHelper::CONTENT_TYPE_POST, $sourceBlogId, $postId, $targetBlogId);

        self::assertSame(SubmissionEntity::SUBMISSION_STATUS_NEW, $submission->getStatus());
        self::assertSame(0, $submission->getIsCloned());

        $this->uploadDownload($category);
        $this->uploadDownload($submission);

        $submissions = $this->getSubmissionManager()->find(
            [
                'content_type' => 'category',
                'source_id' => $categoryId,
                'status' => SubmissionEntity::SUBMISSION_STATUS_COMPLETED,
                'is_cloned' => 0,
            ]
        );

        self::assertCount(1, $submissions);

        $targetCategoryId = ArrayHelper::first($submissions)->getTargetId();

        $submissions = $this->getSubmissionManager()->find(
            [
                'content_type' => 'post',
                'status' => SubmissionEntity::SUBMISSION_STATUS_COMPLETED,
                'is_cloned' => 0,
                'source_id' => $postId,
            ]
        );

        self::assertCount(1, $submissions);

        $submission = ArrayHelper::first($submissions);
        $targetPostId = $submission->getTargetId();

        $siteHelper = $this->get('site.helper');

        $curBlogId = $siteHelper->getCurrentBlogId();
        $targetBlogId = $submission->getTargetBlogId();

        $needChange = $targetBlogId !== $curBlogId;

        if ($needChange) {
            $siteHelper->switchBlogId($targetBlogId);
        }

        $terms = wp_get_post_terms($targetPostId, 'category');

        if ($needChange) {
            $siteHelper->restoreBlogId();
        }

        self::assertCount(1, $terms);

        $term = ArrayHelper::first($terms);

        self::assertSame($targetCategoryId, $term->term_id);
    }

    public function testTranslationAndCloningRelationsOneLevelDeep()
    {
        $this->loadBuiltInFilters();
        #region create posts, images, link them
        $translationBlogId = 2;
        $cloneBlogId = self::CLONE_BLOG_ID;
        $imagesPerPost = 2; // Post thumbnail aka featured image and an image in content
        // TODO add featured images
        $posts = [
            [$this->createPost(title: 'Root Post Title', content: $this->toWpGutenbergParagraph('Root Post content for translation'))],
            [
                $this->createPost(title: 'Child level 2 (1)', content: $this->toWpGutenbergParagraph('Child level 2 (1) content for translation')),
                $this->createPost(title: 'Child level 2 (2)', content: $this->toWpGutenbergParagraph('Child level 2 (2) content for translation')),
            ],
            [
                $this->createPost(title: 'Child level 3 (1-1)', content: $this->toWpGutenbergParagraph('Child level 3 (1-1) content for translation')),
            ],
        ];
        $postSubmissions = [];
        $images = [];
        $imagePointer = 0;
        $expectedImagesCloned = $this->getSiteHelper()->withBlog(self::CLONE_BLOG_ID, function () {
            return $this->getPostCountFromDb(ContentTypeHelper::POST_TYPE_ATTACHMENT);
        });
        $expectedImagesTranslated = $this->getSiteHelper()->withBlog(self::TRANSLATE_BLOG_ID, function () {
            return $this->getPostCountFromDb(ContentTypeHelper::POST_TYPE_ATTACHMENT);
        });
        $expectedPostsCloned = $this->getSiteHelper()->withBlog(self::CLONE_BLOG_ID, function () {
            return $this->getPostCount();
        });
        $expectedPostsTranslated = $this->getSiteHelper()->withBlog(self::TRANSLATE_BLOG_ID, function () {
            return $this->getPostCount();
        });

        for ($_ = 0; $_ < (count($posts[1]) + count($posts[2])) * $imagesPerPost; $_++) {
            $images[] = $this->createAttachment();
        }

        foreach ($posts as $level => $entries) {
            foreach ($entries as $key => $postId) {
                $this->assertIsInt($postId, "Expected post id for level $level, key $key");
                $this->assertNotEquals(0, $postId, "Expected post id for level $level, key $key to be non-zero");
                if ($level === 1) {
                    $post = get_post($postId);
                    $this->assertInstanceOf(\WP_Post::class, $post);
                    set_post_thumbnail($postId, $images[$imagePointer++]); // No, array_unshift won't work here
                    wp_update_post([
                        'ID' => $postId,
                        'post_content' => $post->post_content . $this->toWpGutenbergImage($images[$imagePointer++]),
                        'post_parent' => $posts[0][0]],
                    );
                }
                if ($level === 2) {
                    $post = get_post($postId);
                    $this->assertInstanceOf(\WP_Post::class, $post);
                    set_post_thumbnail($postId, $images[$imagePointer++]);
                    wp_update_post([
                        'ID' => $postId,
                        'post_content' => $post->post_content . $this->toWpGutenbergImage($images[$imagePointer++]),
                        'post_parent' => $posts[1][floor($key / 2)]],
                    );
                }
                if (!array_key_exists($level, $postSubmissions)) {
                    $postSubmissions[$level] = [];
                }
            }
        }
        #endregion
        #region root page
        $originalContent = get_post($posts[0][0])->post_content;
        #region cloning
        $submission = $this->uploadDownload($this->createSubmissionForCloning(ContentTypeHelper::CONTENT_TYPE_POST, $posts[0][0]));
        ++$expectedPostsCloned;
        $this->assertEquals(SubmissionEntity::SUBMISSION_STATUS_COMPLETED, $submission->getStatus(), $submission->getLastError());
        $this->getSiteHelper()->withBlog($cloneBlogId, $this->assertResult($expectedImagesCloned, $originalContent, $expectedPostsCloned, $submission));
        $lastCloneId = $submission->getId();
        #endregion
        #region translation
        $submission = $this->createSubmission(ContentTypeHelper::CONTENT_TYPE_POST, $posts[0][0]);
        $this->assertNotEquals($lastCloneId, $submission->getId());
        $submission = $this->uploadDownload($submission);
        ++$expectedPostsTranslated;
        $this->assertEquals(SubmissionEntity::SUBMISSION_STATUS_COMPLETED, $submission->getStatus(), $submission->getLastError());
        $this->getSiteHelper()->withBlog($translationBlogId, $this->assertResult(
            $expectedImagesTranslated,
            str_replace('Root Post content for translation', '[R~óót P~óst c~óñté~ñt fó~r trá~ñslá~tíóñ]', $originalContent),
            $expectedPostsTranslated,
            $submission,
        ));
        #endregion
        #endregion
        #region child page (level 2)
        $result = $this->verify($posts[1][0], $cloneBlogId, $translationBlogId, $imagesPerPost, $expectedImagesCloned, $expectedPostsCloned, $expectedImagesTranslated, $expectedPostsTranslated, ['(1)'], ['(~1)']);
        #endregion
        #region child page sibling (level 2 sibling)
        $result = $this->verify($posts[1][1], $cloneBlogId, $translationBlogId, $imagesPerPost, $result['expectedImagesCloned'], $result['expectedPostsCloned'], $result['expectedImagesTranslated'], $result['expectedPostsTranslated'], ['(2)'], ['(~2)']);
        #endregion
        #region child > child page (level 3)
        $this->verify($posts[2][0], $cloneBlogId, $translationBlogId, $imagesPerPost, $result['expectedImagesCloned'], $result['expectedPostsCloned'], $result['expectedImagesTranslated'], $result['expectedPostsTranslated'], ['(1-1)'], ['(1~-1)']);
        #endregion
    }

    private function createSubmissionForCloning(string $contentType, int $contentId): SubmissionEntity
    {
        $submission = $this->createSubmission($contentType, $contentId, self::ORIGINAL_BLOG_ID, self::CLONE_BLOG_ID);
        $submission->setIsCloned(1);
        return $this->getSubmissionManager()->storeEntity($submission);
    }

    private function assertResult(int $expectedAttachments, string $expectedContent, int $expectedPosts, SubmissionEntity $submission, ?int $expectedThumbnailId = null): \Closure
    {
        return function () use ($expectedAttachments, $expectedContent, $expectedPosts, $expectedThumbnailId, $submission) {
            $post = get_post($submission->getTargetId());
            $this->assertInstanceOf(\WP_Post::class, $post);
            $this->assertEquals($expectedAttachments, $this->getPostCountFromDb(ContentTypeHelper::POST_TYPE_ATTACHMENT));
            $this->assertEquals($expectedContent, $post->post_content);
            $this->assertEquals($expectedPosts, $this->getPostCount());
            $this->assertEquals($expectedThumbnailId, get_post_thumbnail_id($post));
        };
    }

    private function getContentWithImageIdsReplaced(string $content, array $imageSubmissions, int $blogId): string
    {
        foreach ($imageSubmissions as $imageSubmission) {
            $this->assertInstanceOf(SubmissionEntity::class, $imageSubmission);
            $content = preg_replace("~([:-])({$imageSubmission->getSourceId()})~", '${1}' . $imageSubmission->getTargetId(), $content);
        }
        return $blogId !== self::CLONE_BLOG_ID ? str_replace('/uploads/', "/uploads/sites/$blogId/", $content) : $content;
    }

    private function pseudoPseudoTranslate(string $content): string
    {
        if (str_contains($content, 'level 3')) {
            $result = str_replace(
                ['Child level', 'content for translation'],
                ['[C~híld ~lévé~l', 'c~óñté~ñt fó~r trá~ñslá~tíóñ]'],
                $content,
            );
        } else {
            $result = str_replace(
                ['Child level', 'content for translation'],
                ['[C~híl~d lév~él', 'có~ñtéñ~t fór ~tráñ~slát~íóñ]'],
                $content,
            );
        }
        return str_replace('"large"', '"[l~árgé]"', $result);
    }

    #[ArrayShape(['expectedImagesCloned' => 'int', 'expectedImagesTranslated' => 'int', 'expectedPostsCloned' => 'int', 'expectedPostsTranslated' => 'int'])]
    private function verify(int $postId, int $cloneBlogId, int $translationBlogId, int $imagesPerPost, int $expectedImagesCloned, int $expectedPostsCloned, int $expectedImagesTranslated, int $expectedPostsTranslated, array $search, array $replace): array
    {
        $post = get_post($postId);
        $relations = $this->getContentRelationsDiscoveryService()->getRelations(ContentTypeHelper::CONTENT_TYPE_POST, $postId, [
            $cloneBlogId,
            $translationBlogId
        ]);
        $expectedThumbId = -1;
        $thumbId = get_post_thumbnail_id($postId);
        $this->assertArrayHasKey($cloneBlogId, $relations->getMissingReferences());
        $this->assertArrayHasKey(ContentTypeHelper::POST_TYPE_ATTACHMENT, $relations->getMissingReferences()[$cloneBlogId]);
        $this->assertCount($imagesPerPost, $relations->getMissingReferences()[$cloneBlogId][ContentTypeHelper::POST_TYPE_ATTACHMENT]);
        #region cloning
        $imageSubmissions = [];
        foreach ($relations->getMissingReferences()[$cloneBlogId][ContentTypeHelper::POST_TYPE_ATTACHMENT] as $imageId) {
            $submission = $this->uploadDownload($this->createSubmissionForCloning(ContentTypeHelper::POST_TYPE_ATTACHMENT, $imageId));
            $this->assertEquals(SubmissionEntity::SUBMISSION_STATUS_COMPLETED, $submission->getStatus());
            $this->assertNotEquals(0, $submission->getTargetId());
            if ($submission->getSourceId() !== $thumbId) {
                $imageSubmissions = [$submission];
            } else {
                $expectedThumbId = $submission->getTargetId();
            }
            ++$expectedImagesCloned;
        }
        $submission = $this->uploadDownload($this->createSubmissionForCloning(ContentTypeHelper::CONTENT_TYPE_POST, $postId));
        ++$expectedPostsCloned;
        $this->assertEquals(SubmissionEntity::SUBMISSION_STATUS_COMPLETED, $submission->getStatus());
        $this->getSiteHelper()->withBlog($cloneBlogId, $this->assertResult(
            $expectedImagesCloned,
            $this->getContentWithImageIdsReplaced($post->post_content, $imageSubmissions, $cloneBlogId),
            $expectedPostsCloned,
            $submission,
            $expectedThumbId,
        ));
        #endregion
        #region translation
        $imageSubmissions = [];
        foreach ($relations->getMissingReferences()[$translationBlogId][ContentTypeHelper::POST_TYPE_ATTACHMENT] as $imageId) {
            $submission = $this->uploadDownload($this->createSubmission(ContentTypeHelper::POST_TYPE_ATTACHMENT, $imageId));
            $this->assertEquals(SubmissionEntity::SUBMISSION_STATUS_COMPLETED, $submission->getStatus());
            $this->assertNotEquals(0, $submission->getTargetId());
            if ($submission->getSourceId() !== $thumbId) {
                $imageSubmissions = [$submission];
            } else {
                $expectedThumbId = $submission->getTargetId();
            }
            ++$expectedImagesTranslated;
        }
        $submission = $this->uploadDownload($this->createSubmission(ContentTypeHelper::CONTENT_TYPE_POST, $postId));
        ++$expectedPostsTranslated;
        $this->assertEquals(SubmissionEntity::SUBMISSION_STATUS_COMPLETED, $submission->getStatus());
        $this->getSiteHelper()->withBlog($translationBlogId, $this->assertResult(
            $expectedImagesTranslated,
            str_replace($search, $replace, $this->pseudoPseudoTranslate($this->getContentWithImageIdsReplaced($post->post_content, $imageSubmissions, $translationBlogId))),
            $expectedPostsTranslated,
            $submission,
            $expectedThumbId,
        ));
        #endregion
        return [
            'expectedImagesCloned' => $expectedImagesCloned,
            'expectedImagesTranslated' => $expectedImagesTranslated,
            'expectedPostsCloned' => $expectedPostsCloned,
            'expectedPostsTranslated' => $expectedPostsTranslated,
        ];
    }
}
