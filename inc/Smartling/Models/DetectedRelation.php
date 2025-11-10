<?php

namespace Smartling\Models;

class DetectedRelation
{
    public function __construct(
        private string $contentType,
        private int $id,
        private string $status,
        private string $title,
        private string $url,
        private string $thumbnailUrl,
    ) {
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function toArray(): array
    {
        return [
            'contentType' => $this->contentType,
            'id' => $this->id,
            'status' => $this->status,
            'title' => $this->title,
            'url' => $this->url,
            'thumbnailUrl' => $this->thumbnailUrl,
        ];
    }
}
