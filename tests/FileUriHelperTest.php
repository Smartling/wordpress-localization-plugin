<?php

namespace Smartling\Tests;

use PHPUnit\Framework\TestCase;
use Smartling\Helpers\SiteHelper;
use Smartling\Processors\ContentEntitiesIOFactory;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Tests\Traits\InvokeMethodTrait;
use Smartling\Tests\Traits\SubmissionEntityMock;
use Smartling\Helpers\FileUriHelper;

class FileUriHelperTest extends TestCase
{
    use InvokeMethodTrait;
    use SubmissionEntityMock;

    /**
     * @dataProvider preparePermalinkDataProvider
     */
    public function testPreparePermalink(mixed $string, SubmissionEntity $entity, string $expectedValue): void
    {
        $x = new FileUriHelper($this->createMock(ContentEntitiesIOFactory::class), $this->createMock(SiteHelper::class));
        $this->assertEquals($expectedValue, $x->preparePermalink($string, $entity->getSourceTitle(false)));
    }

    protected function getPreparedSubmissionEntityMock($title, $type, $blogId, $contentId)
    {
        $submissionMock = $this->getSubmissionEntityMock();

        $submissionMock->method('getSourceTitle')->with(false)->willReturn($title);
        $submissionMock->method('getContentType')->willReturn($type);
        $submissionMock->method('getSourceId')->willReturn($blogId);
        $submissionMock->method('getSourceBlogId')->willReturn($contentId);

        return $submissionMock;
    }

    public function preparePermalinkDataProvider(): array
    {
        return
            [
                [
                    'http://nothing.com/blog/my-source-title/',
                    $this->getPreparedSubmissionEntityMock('My Source Title', 'post', 1, 1),
                    '/blog/my-source-title',
                ],
                [
                    (object)['foo'], // simple emulation of the object
                    $this->getPreparedSubmissionEntityMock('My Source Title', 'post', 1, 1),
                    'My Source Title',
                ],
                [
                    'http://nothing.com/?p=123',
                    $this->getPreparedSubmissionEntityMock('My Source Title', 'post', 1, 1),
                    'My Source Title',
                ],
                [
                    ['foo'], // simple emulation of the array
                    $this->getPreparedSubmissionEntityMock('My Source Title', 'post', 1, 1),
                    'My Source Title',
                ],
                [
                    false,
                    $this->getPreparedSubmissionEntityMock('My Source Title', 'post', 1, 1),
                    'My Source Title',
                ],
            ];
    }
}
