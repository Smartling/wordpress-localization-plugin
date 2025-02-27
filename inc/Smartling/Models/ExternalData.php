<?php

namespace Smartling\Models;

class ExternalData
{
    public function __construct(
        private array $strings = [],
        private array $related = [],
        private ?RelatedContentInfo $relatedContentInfo = null,
    ) {
        if ($relatedContentInfo === null) {
            $this->relatedContentInfo = new RelatedContentInfo();
        } else {
            $this->related = $relatedContentInfo->getRelatedContentList();
        }
    }

    public function addRelated(array $related): self
    {
        $result = clone $this;
        $result->related = array_merge_recursive($this->related, $related);

        return $result;
    }

    public function addStrings(array $strings): self
    {
        $result = clone $this;
        $result->strings = array_merge($this->strings, $strings);

        return $result;
    }

    /**
     * @deprecated
     * @see getRelatedContentInfo()
     */
    public function getRelated(): array
    {
        return $this->related;
    }

    public function getRelatedContentInfo(): RelatedContentInfo
    {
        return $this->relatedContentInfo;
    }

    public function getStrings(): array
    {
        return $this->strings;
    }

    public function merge(ExternalData ...$externalData): self
    {
        $strings = $this->strings;
        $related = $this->related;
        $relatedContentInfo = clone $this->relatedContentInfo;
        foreach ($externalData as $data) {
            $strings = array_merge($strings, $data->getStrings());
            $related = array_merge_recursive($related, $data->getRelated());
            $relatedContentInfo = $relatedContentInfo->merge($data->getRelatedContentInfo());
        }

        return new self($strings, $related, $relatedContentInfo);
    }
}
