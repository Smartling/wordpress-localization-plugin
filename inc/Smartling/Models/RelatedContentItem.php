<?php

namespace Smartling\Models;

class RelatedContentItem
{
    function __construct(private Content $content, private string $containerId, private string $path)
    {
    }

    public function getContainerId(): string
    {
        return $this->containerId;
    }

    public function getContent(): Content
    {
        return $this->content;
    }

    public function getPath(): string
    {
        return $this->path;
    }
}
