<?php

namespace Smartling\Tests;

use Smartling\DbAl\WordpressContentEntities\PostEntityStd;
use Smartling\Tests\Traits\DummyLoggerMock;

/**
 * Class PostEntityStdTest
 * @package Smartling\Tests
 * @covers  \Smartling\DbAl\WordpressContentEntities\PostEntityStd
 */
class PostEntityStdTest extends \PHPUnit_Framework_TestCase
{

    use DummyLoggerMock;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|\Smartling\DbAl\WordpressContentEntities\PostEntityStd
     */
    private $wrapperMock;

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|PostEntityStd
     */
    public function getWrapperMock()
    {
        return $this->wrapperMock;
    }

    /**
     * @param \PHPUnit_Framework_MockObject_MockObject|PostEntityStd $wrapperMock
     */
    public function setWrapperMock($wrapperMock)
    {
        $this->wrapperMock = $wrapperMock;
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|\Smartling\DbAl\WordpressContentEntities\PostEntityStd
     */
    private function mockPostEntityStd()
    {
        return $this->getMockBuilder('\Smartling\DbAl\WordpressContentEntities\PostEntityStd')
            ->setConstructorArgs([$this->getLogger(), 'post', []])
            ->setMethods(
                [
                    'getMetadata',
                ]
            )
            ->getMock();
    }


    protected function setUp()
    {
        $this->setWrapperMock($this->mockPostEntityStd());
    }

    /**
     * @covers       \Smartling\DbAl\WordpressContentEntities\PostEntityStd::getMetadata()
     * @dataProvider testGetMetadataDataProvider
     *
     * @param array $rawResult
     * @param array $expectedOutput
     */
    public function testGetMetadata($rawResult, $expectedOutput)
    {
        $wrapper = $this->getWrapperMock();

        $helperMock = $this->getMockBuilder('Smartling\Helpers\WordpressFunctionProxyHelper')
            ->setMethods(['getPostMeta'])
            ->getMock();

        $helperMock->expects(self::any())->method('getPostMeta')->willReturn($rawResult);

        $wrapper->expects(self::any())->method('getMetadata')->willReturn($expectedOutput);

        $wrapper->getMetadata();
    }

    /**
     * @return array
     */
    public function testGetMetadataDataProvider()
    {
        return [
            [
                [
                    'a' => ['value'],
                ],
                [
                    'a' => 'value',
                ],
            ],
            [
                [
                    'a' => ['a', 'b', 'c'],
                ],
                [
                    'a' => ['a', 'b', 'c'],
                ],
            ],
            [
                [
                    'a' => ['s'],
                    'b' => ['a', 'd'],
                ],
                [
                    'a' => 's',
                    'b' => ['a', 'd'],
                ],
            ],
        ];
    }
}