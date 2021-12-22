<?php

namespace IntegrationTests\tests;

use Smartling\Tests\IntegrationTests\SmartlingUnitTestCaseAbstract;

class CloneTest extends SmartlingUnitTestCaseAbstract
{
    public function testNoMediaDuplicationOnCloning(): void
    {
        $targetBlogId = 2;
        switch_to_blog($targetBlogId);
        $attachmentCount = count($this->getAttachments());
        restore_current_blog();

        $childPostId = $this->createPost('post', 'embedded post', 'embedded content');
        $imageId = $this->createAttachment();
        set_post_thumbnail($childPostId, $imageId);
        $rootPostId = $this->createPost('post', 'root post', "<!-- wp:test/post {\"id\":$childPostId} /-->");

        $this->withBlockRules($this->getRulesManager(), [
            'test' => [
                'block' => 'test/post',
                'path' => 'id',
                'replacerId' => 'related|post',
            ],
        ], function () use ($childPostId, $imageId, $rootPostId, $targetBlogId) {
            $relationsDiscoveryService = $this->getContentRelationsDiscoveryService();
            $references = $relationsDiscoveryService->getRelations('post', $rootPostId, [$targetBlogId]);
            $this->assertCount(1, $references->getMissingReferences()[$targetBlogId]['post']);
            $this->assertEquals($childPostId, $references->getMissingReferences()[$targetBlogId]['post'][0]);
            $relationsDiscoveryService->clone([
                'source' => [
                    'contentType' => 'post',
                    'id' => [$rootPostId],
                ],
                'targetBlogIds' => $targetBlogId,
                'relations' => [
                    $targetBlogId => [
                        'post' => [$childPostId],
                        'attachment' => [$imageId],
                    ],
                ],
            ]);
            $this->executeUpload();
        });

        switch_to_blog($targetBlogId);
        $this->assertCount($attachmentCount + 1, $this->getAttachments(), 'Expected exactly one more attachment in target blog after cloning');
        restore_current_blog();
    }

    private function getAttachments(): array
    {
        self::flush_cache();
        return get_posts(['post_type' => 'attachment']);
    }
}
