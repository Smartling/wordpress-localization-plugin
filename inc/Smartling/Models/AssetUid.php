<?php

namespace Smartling\Models;

class AssetUid {
    public function __construct(
        private string $contentType,
        private string $id,
    ) {}

    public static function fromString(string $string): self
    {
        $parts = explode('-', $string);
        if (count($parts) < 2) {
            throw new \InvalidArgumentException('AssetUid string expected to be contentType-id');
        }
        $id = array_pop($parts);

        return new self(implode('-', $parts), $id);
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function __toString(): string
    {
        return $this->contentType . '-' . $this->id;
    }
}
