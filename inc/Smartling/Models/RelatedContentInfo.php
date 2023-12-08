<?php

namespace Smartling\Models;

class RelatedContentInfo {
    /**
     * @var Content[]
     */
    private array $info = [];

    public function addContent(string $path, Content $content): void
    {
        $this->info[$path] = $content;
    }

    public function getInfo(): array
    {
        return $this->info;
    }

    public function getRelatedContentFlat(): array
    {
        $return = [];
        foreach ($this->info as $item) {
            if (!array_key_exists($item->getContentType(), $return)) {
                $return[$item->getContentType()] = [];
            }
            $return[$item->getContentType()][] = $item->getContentId();
        }
        foreach ($return as $key => $value) {
            $return[$key] = array_unique($value);
        }

        return $return;
    }

    public function merge(self $info): self
    {
        $result = clone $this;
        foreach ($info->info as $path => $relation) {
            $result->addContent($path, $relation);
        }

        return $result;
    }
}
