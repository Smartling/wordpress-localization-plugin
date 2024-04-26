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

    public function include(self $info, string $containerId): self
    {
        $result = clone $this;
        $result->info[$containerId] = array_merge($result->info[$containerId] ?? [], $info->info);

        return $result;
    }

    public function merge(self $info): self
    {
        $result = clone $this;
        $result->info = array_merge($result->info, $info->info);

        return $result;
    }
}
