<?php

namespace Smartling\Tests;

use PHPUnit\Framework\TestCase;
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
     *
     * @param mixed $string
     * @param SubmissionEntity $entity
     * @param string $expectedValue
     */
    public function testPreparePermalink($string, SubmissionEntity $entity, string $expectedValue)
    {
        self::assertEquals(
            $this->invokeStaticMethod(
                FileUriHelper::class,
                'preparePermalink',
                [
                    $string,
                    $entity,
                ]
            ),
            $expectedValue
        );
    }

    /**
     * @dataProvider preparePermalinkDataProviderInvalidParams
     *
     * @param mixed $string
     * @param SubmissionEntity $entity
     */
    public function testPreparePermalinkInvalidParams($string, ?SubmissionEntity $entity = null)
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->invokeStaticMethod(FileUriHelper::class, 'preparePermalink', [$string, $entity,]);
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

    public function preparePermalinkDataProviderInvalidParams(): array
    {
        return [
            ['http://nothing.com/blog/my-source-title/', null,],
        ];
    }
}
