<?php

namespace Smartling\Tests\Traits;

/**
 * Class QueueMock
 * @package Smartling\Tests\Traits
 */
trait QueueMock
{
    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|Queue
     */
    private function mockQueue()
    {
        return $this->getMockBuilder('Smartling\Queue\Queue')
            ->setMethods(['enqueue','dequeue'])
            ->disableOriginalConstructor()
            ->getMock();
    }
}