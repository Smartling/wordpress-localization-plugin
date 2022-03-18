<?php

namespace Smartling\Models;

use Smartling\Helpers\PostContentHelper;

class GutenbergBlock
{
    private ?string $blockName; // null for non-Gutenberg blocks. This happens when WP serializes/unserializes content
    private array $attributes;
    private array $innerBlocks;
    private string $innerHtml;
    private array $innerContent;

    /**
     * @param GutenbergBlock[] $innerBlocks
     */
    public function __construct(?string $blockName, array $attributes, array $innerBlocks, string $innerHtml, array $innerContent)
    {
        $this->blockName = $blockName;
        $this->attributes = $attributes;
        $this->innerBlocks = $innerBlocks;
        $this->innerHtml = $innerHtml;
        $this->innerContent = $innerContent;
    }

    public function getBlockName(): ?string
    {
        return $this->blockName;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getSmartlingLockId(): ?string
    {
        return $this->attributes[PostContentHelper::SMARTLING_LOCK_ID] ?? null;
    }

    public function clearInnerBlocks(): self
    {
        $result = clone $this;
        $result->innerBlocks = [];

        return $result;
    }

    /**
     * @return self[]
     */
    public function getInnerBlocks(): array
    {
        return $this->innerBlocks;
    }

    public function setAttributes(array $attributes): self
    {
        $result = clone $this;
        $result->attributes = $attributes;

        return $result;
    }

    /**
     * @param mixed $value
     */
    public function withAttribute(string $attribute, $value): self
    {
        $result = clone $this;
        $result->attributes[$attribute] = $value;

        return $result;
    }

    public function withInnerBlock(GutenbergBlock $block, int $index): self
    {
        $result = clone $this;
        $result->innerBlocks[$index] = $block;

        return $result;
    }

    public function getInnerHtml(): string
    {
        return $this->innerHtml;
    }

    /**
     * @return string|null[]
     */
    public function getInnerContent(): array
    {
        return $this->innerContent;
    }

    public static function fromArray(array $array): self
    {
        $innerBlocks = [];
        if (array_key_exists('innerBlocks', $array)) {
            $innerBlocks = array_map(static function($block) {
                return GutenbergBlock::fromArray($block);
            }, $array['innerBlocks']);
        }
        return new GutenbergBlock(
            $array['blockName'],
            $array['attrs'] ?? [],
            $innerBlocks,
            $array['innerHTML'] ?? '',
            $array['innerContent'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'blockName' => $this->blockName,
            'attrs' => $this->attributes,
            'innerBlocks' => array_map(static function ($block) {
                return $block->toArray();
            }, $this->innerBlocks),
            'innerHTML' => $this->innerHtml,
            'innerContent' => $this->innerContent,
        ];
    }
}
