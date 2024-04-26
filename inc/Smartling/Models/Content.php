<?php

namespace Smartling\Models;

class Content {
    public function __construct(
        private int $contentId,
        private string $contentType,
    ) {
    }

    public function getContentId(): int
    {
        return $this->contentId;
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }
}
