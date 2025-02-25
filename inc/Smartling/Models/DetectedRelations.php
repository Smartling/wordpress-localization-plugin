<?php

namespace Smartling\Models;

class DetectedRelations
{
    public const string ORIGINAL_REFERENCES_KEY = 'originalReferences';
    public const string MISSING_TRANSLATED_REFERENCES_KEY = 'missingTranslatedReferences';
    private array $missingReferences = [];

    public function __construct(private readonly array $originalReferences)
    {
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
