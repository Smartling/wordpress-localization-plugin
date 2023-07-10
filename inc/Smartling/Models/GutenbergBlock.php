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

    public function __toString()
    {
        return $this->serializeBlock($this);
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

    /**
     * @return self[]
     */
    public function getInnerBlocks(): array
    {
        return $this->innerBlocks;
    }

    public function withAttributes(array $attributes): self
    {
        $result = clone $this;
        $result->attributes = $attributes;

        return $result;
    }

    public function withInnerContent(array $innerContent): self
    {
        $result = clone $this;
        $result->innerContent = $innerContent;

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
     * @return (string|null)[]
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

    private function getCommentDelimitedBlockContent(?string $name, array $attributes, string $content): string
    {
        if ($name === null) {
            return $content;
        }

        $name = $this->stripCoreBlockNamespace($name);
        $attributeString = empty($attributes) ? '' : $this->serializeBlockAttributes($attributes) . ' ';

        if ($content === '') {
            return sprintf('<!-- wp:%s %s/-->', $name, $attributeString);
        }

        return sprintf('<!-- wp:%1$s %2$s-->%3$s<!-- /wp:%1$s -->', $name, $attributeString, $content);
    }

    public function serializeBlock(self $block): string
    {
        $content = '';

        $index = 0;
        foreach ($block->innerContent as $chunk) {
            $content .= is_string($chunk) ? $chunk : $this->serializeBlock($block->innerBlocks[$index++]);
        }

        return $this->getCommentDelimitedBlockContent(
            $block->blockName,
            $block->attributes,
            $content,
        );
    }

    private function serializeBlockAttributes(array $attributes): string
    {
        return preg_replace(
            ['/</', '/>/', '/&/', '/\\\\"/'],
            ['\\u003c', '\\u003e', '\\u0026', '\\u0022'],
            str_replace("--", '\\u002d\\u002d', json_encode($attributes)),
        );
    }

    private function stripCoreBlockNamespace(string $name): string
    {
        $prefix = 'core/';
        if (0 === strpos($name, $prefix)) {
            return substr($name, strlen($prefix));
        }
        return $name;
    }
}
