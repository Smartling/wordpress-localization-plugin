<?php

namespace Smartling\Models;

class Content {
    public function __construct(
        private int $contentId,
        private string $contentType,
    ) {
    }

    public function getId(): int
    {
        return $this->contentId;
    }

    public function getType(): string
    {
        return $this->contentType;
    }
}
