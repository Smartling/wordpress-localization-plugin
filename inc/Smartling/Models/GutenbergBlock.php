<?php

namespace Smartling\Models;

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

    /**
     * @return self[]
     */
    public function getInnerBlocks(): array
    {
        return $this->innerBlocks;
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
