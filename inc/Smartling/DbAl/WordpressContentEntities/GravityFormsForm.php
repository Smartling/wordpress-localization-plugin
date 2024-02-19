<?php

namespace Smartling\DbAl\WordpressContentEntities;

use Smartling\ContentTypes\ExternalContentGravityForms;
use Smartling\WP\View\BulkSubmitScreenRow;

class GravityFormsForm extends EntityBase {
    private string $displayMeta;
    private ?int $id;
    private string $title;
    private ?string $updated;

    public function __construct(string $displayMeta, ?int $id, string $title, ?string $updated)
    {
        $this->displayMeta = $displayMeta;
        $this->id = $id;
        $this->title = $title;
        $this->updated = $updated;
    }

    public function forInsert(): self
    {
        return new self($this->displayMeta, null, $this->title, $this->updated);
    }

    public function fromArray(array $array): self
    {
        return new self($array['displayMeta'], $array['id'], $array['title'], $array['updated']);
    }

    public function getDisplayMeta(): string
    {
        return $this->displayMeta;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $result = clone $this;
        $this->id = $id;

        return $result;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function toArray(): array
    {
        return [
            'displayMeta' => $this->displayMeta,
            'id' => $this->id,
            'title' => $this->title,
            'updated' => $this->updated,
        ];
    }

    public function toBulkSubmitScreenRow(): BulkSubmitScreenRow
    {
        return new BulkSubmitScreenRow($this->id, $this->title, ExternalContentGravityForms::CONTENT_TYPE, updated: $this->updated);
    }
}
