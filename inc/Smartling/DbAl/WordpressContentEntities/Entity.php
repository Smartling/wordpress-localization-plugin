<?php

namespace Smartling\DbAl\WordpressContentEntities;

use Smartling\WP\View\BulkSubmitScreenRow;

interface Entity {
    public function fromArray(array $array): self;

    public function getId(): ?int;

    public function getRelatedContentTypes(): array;

    public function getTitle(): string;

    public function toArray(): array;

    public function toBulkSubmitScreenRow(): BulkSubmitScreenRow;
}
