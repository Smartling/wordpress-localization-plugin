<?php

namespace Smartling\Models;

class UploadQueueItem
{
    public function __construct(private int $submissionId, private string $jobUid)
    {
    }

    public function getJobUid(): string
    {
        return $this->jobUid;
    }

    public function getSubmissionId(): int
    {
        return $this->submissionId;
    }
}
