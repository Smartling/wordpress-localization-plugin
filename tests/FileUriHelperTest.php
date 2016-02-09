<?php

namespace Smartling\Tests;

use Smartling\Submissions\SubmissionEntity;
use Smartling\Tests\Mocks\SubmissionEntityMockHelper;
use Smartling\Tests\Traits\InvokeMethodTrait;


/**
 * Class FileUriHelperTest
 * Test class for \Smartling\Helpers\FileUriHelper.
 * @package Smartling\Tests
 */
class FileUriHelperTest extends \PHPUnit_Framework_TestCase
{
    use InvokeMethodTrait;

    const FileUriClassFullName = 'Smartling\Helpers\FileUriHelper';

    /**
     * @covers       FileUriHelper::preparePermalink
     * @dataProvider preparePermalinkDataProvider
     *
     * @param string           $string
     * @param SubmissionEntity $entity
     * @param string           $expectedValue
     */
    public function testPreparePermalink($string, $entity, $expectedValue)
    {
        self::assertEquals(
            $this->invokeStaticMethod(
                self::FileUriClassFullName,
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
     * @covers       FileUriHelper::preparePermalink
     * @dataProvider preparePermalinkDataProviderInvalidParams
     *
     * @param string           $string
     * @param SubmissionEntity $entity
     *
     * @expectedException \InvalidArgumentException
     */
    public function testPreparePermalinkInvalidParams($string, $entity)
    {
        $this->invokeStaticMethod(
            self::FileUriClassFullName,
            'preparePermalink',
            [
                $string,
                $entity,
            ]
        );
    }

    protected function getSubmissionEntityMock($title, $type, $blogId, $contentId)
    {
        $submissionMock = SubmissionEntityMockHelper::getRawMock(
            $this->getMockBuilder(SubmissionEntityMockHelper::CLASS_NAME)
        );
        $submissionMock->expects(self::any())
                       ->method('getSourceTitle')
                       ->with(false)
                       ->willReturn($title);
        $submissionMock->expects(self::any())
                       ->method('getContentType')
                       ->willReturn($type);
        $submissionMock->expects(self::any())
                       ->method('getSourceId')
                       ->willReturn($blogId);
        $submissionMock->expects(self::any())
                       ->method('getSourceBlogId')
                       ->willReturn($contentId);

        return $submissionMock;
    }

    /**
     * Data provider for testPreparePermalink method.
     *
     * @return array
     */
    public function preparePermalinkDataProvider()
    {
        return [
            [
                'http://nothing.com/blog/my-source-title/',
                $this->getSubmissionEntityMock('My Source Title', 'post', 1, 1),
                '/blog/my-source-title',
            ],
            [
                (object)['foo'], // simple emulation of the object
                $this->getSubmissionEntityMock('My Source Title', 'post', 1, 1),
                'My Source Title',
            ],
            [
                'http://nothing.com/?p=123',
                $this->getSubmissionEntityMock('My Source Title', 'post', 1, 1),
                'My Source Title',
            ],
            [
                ['foo'], // simple emulation of the array
                $this->getSubmissionEntityMock('My Source Title', 'post', 1, 1),
                'My Source Title',
            ],
            [
                false, // simple emulation of the array
                $this->getSubmissionEntityMock('My Source Title', 'post', 1, 1),
                'My Source Title',
            ],
        ];
    }

    /**
     * Data provider for testPreparePermalinkInvalidParams method.
     *
     * @return array
     */
    public function preparePermalinkDataProviderInvalidParams()
    {
        return [
            [
                'http://nothing.com/blog/my-source-title/',
                null,
            ],
            [
                'http://nothing.com/blog/my-source-title/',
                $this->getSubmissionEntityMock('', 'post', 1, 1),
            ],
        ];
    }
}
