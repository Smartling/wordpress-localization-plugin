<?php

namespace Smartling\Models;

class DuplicateSubmissionDetails
{
    public function __construct(
        private string $contentType,
        private int $sourceBlogId,
        private int $sourceId,
        private int $targetBlogId,
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
}
