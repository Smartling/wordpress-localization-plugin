<?php

namespace Smartling\Models;

class Content {
    public function __construct(
        private int $contentId,
        private string $contentType,
        private bool $remapOnly = false,
    ) {
    }

    /**
     * Remap-only content has its id replaced with the translated one when it is already translated,
     * but is never sent for translation by itself
     */
    public function isRemapOnly(): bool
    {
        return $this->remapOnly;
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
