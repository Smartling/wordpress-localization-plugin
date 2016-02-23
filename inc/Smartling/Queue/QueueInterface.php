<?php

namespace Smartling\Queue;

/**
 * Interface QueueInterface
 *
 * @package Smartling\Queue
 */
interface QueueInterface
{
    /**
     * Adds an array to the queue
     *
     * @param array       $value
     * @param string|null $queue
     */
    public function enqueue(array $value, $queue = null);

    /**
     * @param null $queue
     *
     * @return array
     */
    public function dequeue($queue = null);
}