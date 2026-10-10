<?php

namespace Smartling\Models;

class Content {
    public function __construct(
        private int $contentId,
        private string $contentType,
        private bool $remapOnly = false,
        private bool $termTaxonomyId = false,
    ) {
    }

    /**
     * The setting this content was found in stores a term_taxonomy_id instead of a term_id (e.g. Elementor Pro queries).
     * Until converted, getId() returns the term_taxonomy_id; the converted content has the term_id and keeps the flag,
     * so the translated term_id can be converted back before it is written.
     */
    public function isTermTaxonomyId(): bool
    {
        return $this->termTaxonomyId;
    }

    public function withId(int $id): self
    {
        $result = clone $this;
        $result->contentId = $id;

        return $result;
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
