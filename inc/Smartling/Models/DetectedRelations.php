<?php

namespace Smartling\Models;

class DetectedRelations
{
    public const ORIGINAL_REFERENCES_KEY = 'originalReferences';
    private array $originalReferences;

    public function __construct(array $originalReferences)
    {
        $this->originalReferences = $originalReferences;
    }

    public function getOriginalReferences(): array
    {
        return $this->originalReferences;
    }

    public function toArray(): array
    {
        return [
            self::ORIGINAL_REFERENCES_KEY => $this->originalReferences,
        ];
    }
}
