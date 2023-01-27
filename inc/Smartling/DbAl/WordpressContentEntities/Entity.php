<?php

namespace Smartling\DbAl\WordpressContentEntities;

use JetBrains\PhpStorm\ArrayShape;

interface Entity {
    public function fromArray(array $array): self;

    public function getId(): ?int;

    public function getTitle(): string;

    public function toArray(): array;

    #[ArrayShape([
        'author' => 'string',
        'id' => 'mixed',
        'status' => 'string',
        'title' => 'string',
        'locales' => 'string',
        'type' => 'string',
        'updated' => 'string',
    ])]
    public function toBulkSubmitScreenRow(): array;
}
