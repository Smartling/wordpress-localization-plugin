<?php

namespace Smartling\Queue;

interface QueueInterface
{
    public const VIRTUAL_UPLOAD_QUEUE = 'upload-queue';
    public const QUEUE_NAME_DOWNLOAD_QUEUE = 'download-queue';
    public const QUEUE_NAME_LAST_MODIFIED_CHECK_AND_FAIL_QUEUE = 'last-modified-check-and-fail-queue';
    public const QUEUE_NAME_LAST_MODIFIED_CHECK_QUEUE = 'last-modified-check-queue';

    public function isVirtual(string $queue): bool;

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

    public function purge(?string $queue = null): void;

    /**
     * @return array['queue' => elements_count]
     */
    public function stats(): array;
}
