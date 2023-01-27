<?php

namespace Smartling\Helpers;

class GutenbergReplacementRule
{
    private const STRING_FORMAT = 'blockType="%s", propertyPath="%s", replacerId="%s"';
    private string $blockType;
    private string $propertyPath;
    private string $replacerId;

    public function __construct(string $blockType, string $propertyPath, string $replacerId)
    {
        $this->blockType = $blockType;
        $this->propertyPath = $propertyPath;
        $this->replacerId = $replacerId;
    }

    public function __toString()
    {
        return sprintf(self::STRING_FORMAT, addslashes($this->blockType), addslashes($this->propertyPath), addslashes($this->replacerId));
    }

    public static function fromString(string $string): self
    {
        $parts = [];
        preg_match('~' . str_replace('%s', '(.+)', self::STRING_FORMAT) . '~', stripslashes($string), $parts);
        if (count($parts) !== 4) {
            throw new \InvalidArgumentException('Malformed string');
        }

        return new GutenbergReplacementRule($parts[1], $parts[2], $parts[3]);
    }

    public function toArray(): array
    {
        return [
            'block' => $this->blockType,
            'path' => $this->propertyPath,
            'replacerId' => $this->replacerId,
        ];
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
