<?php

namespace Smartling\Models;

class ElementorDynamicTagProcessor
{
    /**
     * @param ?callable $callable
     */
    public function __construct(
        private string $path,
        private $callable = null,
    ) {
        if ($callable !== null && !is_callable($callable)) {
            throw new \InvalidArgumentException("Callable expected");
        }
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
