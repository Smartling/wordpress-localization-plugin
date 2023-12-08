<?php

namespace Smartling\ContentTypes\Elementor;

use Smartling\Models\RelatedContentInfo;

interface Element {
    public function fromArray(array $array): self;
    public function getId(): string;
    public function getRelated(): RelatedContentInfo;
    public function getTranslatableStrings(): array;
    public function getType(): string;
    public function toArray(): array;
}
