<?php

namespace Smartling\Submissions;

class SubmissionSimple implements Submission {

    public function __construct(
        private string $contentType = '',
        private int $sourceBlogId = 0,
        private int $sourceId = 0,
        private string $sourceTitle = '',
        private int $targetBlogId = 0,
        private int $targetId = 0,
    ) {
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }

    public function getSourceBlogId(): int
    {
        return $this->sourceBlogId;
    }

    public function getSourceId(): int
    {
        return $this->sourceId;
    }

    public function getSourceTitle(): string
    {
        return $this->sourceTitle;
    }

    public function getTargetBlogId(): int
    {
        return $this->targetBlogId;
    }

    public function getTargetId(): int
    {
        return $this->targetId;
    }
}
