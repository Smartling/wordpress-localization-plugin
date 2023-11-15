<?php

namespace Smartling\Helpers;

use PHPUnit\Framework\TestCase;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

class WordpressLinkHelperTest extends TestCase {

    /**
     * @dataProvider testGetTargetBlogLinkProvider
     */
    public function testGetTargetBlogLink(string|false $getBlogPermalinkResult, ?string $expected)
    {
        $sourcePostId = 1;
        $targetBlogId = 2;
        $targetPostId = 3;

        $submission = $this->createMock(SubmissionEntity::class);
        $submission->method('getTargetId')->willReturn($targetPostId);

        $submissionManager = $this->createMock(SubmissionManager::class);
        $submissionManager->expects($this->once())->method('find')->with([
            SubmissionEntity::FIELD_TARGET_BLOG_ID => $targetBlogId,
            SubmissionEntity::FIELD_SOURCE_ID => $sourcePostId,
        ])->willReturn([$submission]);

        $wordpressProxy = $this->createMock(WordpressFunctionProxyHelper::class);
        $wordpressProxy->expects($this->once())->method('get_blog_permalink')->with($targetBlogId, $targetPostId)
            ->willReturn($getBlogPermalinkResult);
        $wordpressProxy->expects($this->once())->method('url_to_postid')->willReturn($sourcePostId);

        $x = new WordpressLinkHelper($submissionManager, $wordpressProxy);
        $this->assertEquals($expected, $x->getTargetBlogLink('https://example.com/?post=1', $targetBlogId));
    }

    public function testGetTargetBlogLinkProvider(): array
    {
        return [
            'Target exists' => [
                'https://translated.example.com/?post=3',
                'https://translated.example.com/?post=3',
            ],
            'Target does not exist' => [
                false,
                null,
            ],
        ];
    }
}
