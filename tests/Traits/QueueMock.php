<?php

namespace Smartling\Tests\Traits;

use PHPUnit\Framework\MockObject\MockObject;
use Smartling\Queue\Queue;

trait QueueMock
{
    /**
     * @return MockObject|Queue
     */
    private function mockQueue()
    {
        return $this->createPartialMock(Queue::class, ['enqueue','dequeue']);
    }
}
