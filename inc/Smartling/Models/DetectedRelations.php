<?php

namespace Smartling\Models;

class DetectedRelations
{
    public const ORIGINAL_REFERENCES_KEY = 'originalReferences';
    public const MISSING_TRANSLATED_REFERENCES_KEY = 'missingTranslatedReferences';
    private array $originalReferences;
    private array $missingReferences = [];

    public function __construct(array $originalReferences)
    {
        $this->originalReferences = $originalReferences;
    }

    public function addMissingReference(int $targetBlogId, string $contentType, int $id): void
    {
        $this->missingReferences[$targetBlogId][$contentType][] = $id;
    }

    public function getOriginalReferences(): array
    {
        return $this->originalReferences;
    }

    public function getMissingReferences(): array
    {
        return $this->missingReferences;
    }

    public function toArray(): array
    {
        return [
            self::ORIGINAL_REFERENCES_KEY => $this->originalReferences,
            self::MISSING_TRANSLATED_REFERENCES_KEY => $this->missingReferences,
        ];
    }
}
