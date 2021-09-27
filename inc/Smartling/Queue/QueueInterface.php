<?php

namespace Smartling\Queue;

/**
 * Interface QueueInterface
 * @package Smartling\Queue
 */
interface QueueInterface
{
    public const QUEUE_NAME_DOWNLOAD_QUEUE = 'download-queue';

    public const QUEUE_NAME_LAST_MODIFIED_CHECK_QUEUE = 'last-modified-check-queue';

    /**
     * Adds an array to the queue
     *
     * @param array  $value
     * @param string $queue
     */
    public function enqueue(array $value, $queue);

    /**
     * @param string $queue
     *
     * @return array|bool
     */
    public function dequeue($queue);

    /**
     * @param string|null $queue
     *
     * @return void
     */
    public function purge($queue = null);

    /**
     * @return array['queue' => elements_count]
     */
    public function stats();
}