<?php

namespace Smartling\Models;

class ElementorDynamicTagProcessor
{
    public function __construct(
        private string $path,
        private $callable = null,
    ) {
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getCallable(): ?callable
    {
        return $this->callable;
    }
}
