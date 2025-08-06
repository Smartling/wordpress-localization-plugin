<?php

namespace Smartling\Models;

use Smartling\Helpers\ArrayHelper;

class RelatedContentInfo {
    private ArrayHelper $arrayHelper;
    private array $info;

    public function __construct(array $info = [])
    {
        $this->arrayHelper = new ArrayHelper();
        foreach ($this->arrayHelper->flatten($info) as $item) {
            assert($item instanceof Content);
        }
        $this->info = $info;
    }

    public function addContent(Content $content, string $containerId, string $path): void
    {
        if (!array_key_exists($containerId, $this->info)) {
            $this->info[$containerId] = [];
        }
        $this->info[$containerId][$path] = $content;
    }

    public function getInfo(): array
    {
        return $this->info;
    }

    /**
     * @return Content[]
     */
    public function getOwnRelatedContent(string $containerId): array
    {
        return array_filter($this->info[$containerId] ?? [], static function ($item) {
            return $item instanceof Content;
        });
    }

    /**
     * @return Content[]
     */
    public function getRelatedContentList(): array
    {
        $flat = $this->arrayHelper->flatten($this->info);
        $return = [];
        foreach ($flat as $item) {
            assert($item instanceof Content);
            if (!array_key_exists($item->getType(), $return)) {
                $return[$item->getType()] = [];
            }
            $return[$item->getType()][] = $item->getId();
        }
        foreach ($return as $key => $value) {
            $return[$key] = array_unique($value);
        }

        return $return;
    }

    public function include(self $info, string $containerId): self
    {
        $result = clone $this;
        $result->info[$containerId] = $this->arrayMergePreserveKeys($this->info, $info->info);

        return $result;
    }

    /**
     * Elementor sometimes generates ids as numerical strings e.g. "12345678"
     * array_merge and array_merge_recursive don't preserve numerical keys
    */
    private function arrayMergePreserveKeys(array $array1, array $array2): array
    {
        foreach ($array2 as $key => $value) {
            if (is_array($value) && isset($array1[$key]) && is_array($array1[$key])) {
                $array1[$key] = $this->arrayMergePreserveKeys($array1[$key], $value);
            } else {
                $array1[$key] = $value;
            }
        }
        return $array1;
    }

    public function merge(self $info): self
    {
        $result = clone $this;
        $result->info = array_merge($result->info, $info->info);

        return $result;
    }
}
