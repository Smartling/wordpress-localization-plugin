<?php

namespace Smartling\Submissions;

class SubmissionSimple implements Submission {

    public function __construct(
        private string $contentType,
        private int $sourceBlogId,
        private int $sourceId,
        private int $targetBlogId,
        private int $targetId,
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

    public function getTargetBlogId(): int
    {
        return $this->targetBlogId;
    }

    public function getTargetId(): int
    {
        return $this->targetId;
    }
}
