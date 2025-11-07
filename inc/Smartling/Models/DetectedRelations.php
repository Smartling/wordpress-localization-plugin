<?php

namespace Smartling\Models;

class DetectedRelations
{
    public const REFERENCES_KEY = 'references';
    private array $references;

    /**
     * @param DetectedRelation[] $references
     */
    public function __construct(array $references)
    {
        $this->references = $references;
    }

    /**
     * @return DetectedRelation[]
     */
    public function getReferences(): array
    {
        return $this->references;
    }

    public function toArray(): array
    {
        return [
            self::REFERENCES_KEY =>
                array_map(static function (DetectedRelation $relation) {
                    return $relation->toArray();
                }, $this->references),
        ];
    }
}
