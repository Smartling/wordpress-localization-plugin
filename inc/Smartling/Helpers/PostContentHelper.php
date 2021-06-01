<?php

namespace Smartling\Helpers;

use Smartling\Models\GutenbergBlock;

class PostContentHelper
{
    private GutenbergBlockHelper $blockHelper;

    public function __construct(GutenbergBlockHelper $blockHelper)
    {
        $this->blockHelper = $blockHelper;
    }

    public function applyTranslation(string $original, string $translated, array $lockedFields): string
    {
        $result = '';
        $originalBlocks = $this->blockHelper->parseBlocks($original);
        $translatedBlocks = $this->blockHelper->parseBlocks($translated);

        foreach ($lockedFields as $lockedField) {
            $lockedField = preg_replace('~^entity/post_content/blocks/~', '', $lockedField);
            $parts = explode('/', $lockedField);
            $index = array_shift($parts);
            if (count($parts) > 0) {
                $translatedBlocks[$index] = $this->applyLockedField($parts, $originalBlocks[$index], $translatedBlocks[$index]);
            } else {
                $translatedBlocks[$index] = $originalBlocks[$index];
            }
        }

        foreach ($translatedBlocks as $block) {
            $result .= serialize_block($block->toArray());
        }

        return $result;
    }

    private function applyLockedField(array $pathParts, GutenbergBlock $originalBlock, GutenbergBlock $translatedBlock): GutenbergBlock
    {
        $index = array_shift($pathParts);
        while (count($pathParts) > 0) {
            $this->applyLockedField($pathParts, $originalBlock->getInnerBlocks()[$index], $translatedBlock->getInnerBlocks()[$index]);
        }

        return $translatedBlock->withInnerBlock($originalBlock->getInnerBlocks()[$index], $index);
    }
}
