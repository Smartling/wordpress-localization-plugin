<?php

namespace Smartling\Helpers;

use PHPUnit\Framework\TestCase;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

class LinkHelperTest extends TestCase {

    private int $targetBlogId = 2;

    public function testGetTargetBlogLinkSourcePostNotFound(): void
    {
        $wordpressProxy = $this->createMock(WordpressFunctionProxyHelper::class);
        $wordpressProxy->method('url_to_postid')->willReturn(0);
        $x = new WordpressLinkHelper($this->createMock(SubmissionManager::class), $wordpressProxy);
        $this->assertEquals(null, $x->getTargetBlogLink('https://example.com', $this->targetBlogId));
    }

    public function testGetTargetBlogLinkSubmissonNotFound()
    {
        $sourcePostId = 1;
        $submissionManager = $this->createMock(SubmissionManager::class);
        $submissionManager->expects($this->once())->method('find')->with([
            SubmissionEntity::FIELD_SOURCE_ID => $sourcePostId,
            SubmissionEntity::FIELD_TARGET_BLOG_ID => $this->targetBlogId,
        ])->willReturn([]);
        $wordpressProxy = $this->createMock(WordpressFunctionProxyHelper::class);
        $wordpressProxy->method('url_to_postid')->willReturn($sourcePostId);
        $x = new WordpressLinkHelper($submissionManager, $wordpressProxy);
        $this->assertEquals(null, $x->getTargetBlogLink('https://example.com', $this->targetBlogId));
    }

    public function testGetTargetBlogLinkTargetPostNotFound()
    {
        $sourcePostId = 1;
        $targetPostId = 3;
        $submission = new SubmissionEntity();
        $submission->setTargetId($targetPostId);
        $submissionManager = $this->createMock(SubmissionManager::class);
        $submissionManager->expects($this->once())->method('find')->with([
            SubmissionEntity::FIELD_SOURCE_ID => $sourcePostId,
            SubmissionEntity::FIELD_TARGET_BLOG_ID => $this->targetBlogId,
        ])->willReturn([$submission]);
        $wordpressProxy = $this->createMock(WordpressFunctionProxyHelper::class);
        $wordpressProxy->method('url_to_postid')->willReturn($sourcePostId);
        $wordpressProxy->method('get_blog_permalink')->willReturn(null);
        $x = new WordpressLinkHelper($submissionManager, $wordpressProxy);
        $this->assertEquals(null, $x->getTargetBlogLink('https://example.com', $this->targetBlogId));
    }

    public function testGetTargetBlogLink()
    {
        $sourcePostId = 1;
        $targetPostId = 3;
        $expected = 'https://translated.example.com/path/to/post';
        $submission = new SubmissionEntity();
        $submission->setTargetId($targetPostId);
        $submissionManager = $this->createMock(SubmissionManager::class);
        $submissionManager->expects($this->once())->method('find')->with([
            SubmissionEntity::FIELD_SOURCE_ID => $sourcePostId,
            SubmissionEntity::FIELD_TARGET_BLOG_ID => $this->targetBlogId,
        ])->willReturn([$submission]);
        $wordpressProxy = $this->createMock(WordpressFunctionProxyHelper::class);
        $wordpressProxy->method('url_to_postid')->willReturn($sourcePostId);
        $wordpressProxy->expects($this->once())->method('get_blog_permalink')->willReturn($expected);
        $x = new WordpressLinkHelper($submissionManager, $wordpressProxy);
        $this->assertEquals($expected, $x->getTargetBlogLink('https://example.com', $this->targetBlogId));
    }

    /**
     * @dataProvider testReplaceHostProvider
     */
    public function testReplaceHost(string $sourceUrl, ?string $expected): void
    {
        $submissionManager = $this->createMock(SubmissionManager::class);
        $wordpressProxy = $this->createMock(WordpressFunctionProxyHelper::class);
        $wordpressProxy->method('get_home_url')->willReturnCallback(function (int $blogId = null) {
            if ($blogId === null) {
                return 'https://example.com';
            }

            return 'https://translated.example.com';
        });
        $x = new WordpressLinkHelper($submissionManager, $wordpressProxy);
        $this->assertEquals($expected, $x->replaceHost($sourceUrl, $this->targetBlogId));
    }

    public function testReplaceHostProvider(): array
    {
        return [
            'should return null on invalid urls' => ['', null],
            'should replace current blog host' => ['http://example.com/test', 'http://translated.example.com/test'],
            'should not replace external hosts' => ['http://external.example.com/test', null],
            'should process any valid url' => ['http://example.com/example.com?page=15', 'http://translated.example.com/example.com?page=15'],
        ];
    }
}
