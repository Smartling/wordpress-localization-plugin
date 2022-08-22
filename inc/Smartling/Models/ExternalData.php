<?php

namespace Smartling\Models;

class ExternalData {
    private array $related;
    private array $strings;

    public function __construct(array $strings = [], array $related = [])
    {
        $this->related = $related;
        $this->strings = $strings;
    }

    public function addRelated(array $related): self
    {
        $result = clone $this;
        $result->related = array_merge($this->related, $related);

        return $result;
    }

    public function addStrings(array $strings): self
    {
        $result = clone $this;
        $result->strings = array_merge($this->strings, $strings);

        return $result;
    }

    public function getRelated(): array
    {
        return $this->related;
    }

    public function getStrings(): array
    {
        return $this->strings;
    }

    public function merge(ExternalData ...$externalData): self
    {
        $strings = $this->strings;
        $related = $this->related;
        foreach ($externalData as $data) {
            $strings = array_merge($strings, $data->getStrings());
            $related = array_merge_recursive($related, $data->getRelated());
        }

        return new self($strings, $related);
    }
}
