<?php

namespace Smartling\Helpers;

class GutenbergReplacementRule
{
    private string $blockType;
    private string $propertyPath;
    private string $replacerId;

    public function __construct(string $blockType, string $propertyPath, string $replacerId)
    {
        $this->blockType = $blockType;
        $this->propertyPath = $propertyPath;
        $this->replacerId = $replacerId;
    }

    public function getBlockType(): string
    {
        return $this->blockType;
    }

    public function getPropertyPath(): string
    {
        return $this->propertyPath;
    }

    public function getReplacerId(): string
    {
        return $this->replacerId;
    }
}
