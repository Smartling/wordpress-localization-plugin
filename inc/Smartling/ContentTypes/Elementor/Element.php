<?php

namespace Smartling\ContentTypes\Elementor;

interface Element {
    public function fromArray(array $array): self;
    public function getId(): string;
    public function getRelated(): array;
    public function getTranslatableStrings(): array;
    public function getType(): string;
    public function toArray(): array;
}
