<?php

namespace Smartling\Queue;

interface QueueInterface
{
    public const string UPLOAD_QUEUE = 'upload-queue';
    public const string QUEUE_NAME_DOWNLOAD_QUEUE = 'download-queue';
    public const string QUEUE_NAME_LAST_MODIFIED_CHECK_AND_FAIL_QUEUE = 'last-modified-check-and-fail-queue';
    public const string QUEUE_NAME_LAST_MODIFIED_CHECK_QUEUE = 'last-modified-check-queue';

    public function isVirtual(string $queue): bool;

    public function enqueue(array $value, string $queue): void;

    public function dequeue(string $queue): mixed;

    public function purge(?string $queue = null): void;

    public function stats(): array;
}
