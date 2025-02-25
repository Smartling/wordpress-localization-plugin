<?php

namespace Smartling\Models;

readonly class DuplicateSubmissionDetails
{
    public function __construct(
        public string $contentType,
        public int $sourceBlogId,
        public int $sourceId,
        public int $targetBlogId,
    ) {
    }
}
