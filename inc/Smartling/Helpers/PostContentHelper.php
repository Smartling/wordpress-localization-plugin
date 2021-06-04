<?php

namespace Smartling\Helpers;

use Psr\Log\LoggerInterface;
use Smartling\Models\GutenbergBlock;
use Smartling\MonologWrapper\MonologWrapper;

class PostContentHelper
{
    private GutenbergBlockHelper $blockHelper;
    private LoggerInterface $logger;

    public function __construct(GutenbergBlockHelper $blockHelper)
    {
        $this->blockHelper = $blockHelper;
        $this->logger = MonologWrapper::getLogger();
    }

    public function applyTranslation(string $original, string $translated, array $lockedFields): string
    {
        $result = '';
        $originalBlocks = $this->blockHelper->parseBlocks($original);
        $translatedBlocks = $this->blockHelper->parseBlocks($translated);

        foreach ($lockedFields as $lockedField) {
            $count = 0;
            $lockedField = preg_replace('~^entity/post_content/blocks/~', '', $lockedField, -1, $count);
            if ($count === 1) {
                $parts = explode('/', $lockedField);
                $index = array_shift($parts);
                if (!array_key_exists($index, $originalBlocks)) {
                    $this->logger->notice("Unable to get content for locked Gutenberg block $lockedField, skipping lock");
                    continue;
                }
                if (count($parts) > 0) {
                    $translatedBlocks[$index] = $this->applyLockedField($parts, $originalBlocks[$index], $translatedBlocks[$index]);
                } else {
                    $translatedBlocks[$index] = $originalBlocks[$index];
                }
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
        $originalInnerBlocks = $originalBlock->getInnerBlocks();
        if (!array_key_exists($index, $originalInnerBlocks)) {
            $this->logger->notice("Unable to get content for locked Gutenberg block " .
                implode('/', array_merge($pathParts, [$index])) .
                ", skipping lock");
            return $translatedBlock;
        }
        while (count($pathParts) > 0) {
            $this->applyLockedField($pathParts, $originalInnerBlocks[$index], $translatedBlock->getInnerBlocks()[$index]);
        }

        return $translatedBlock->withInnerBlock($originalInnerBlocks[$index], $index);
    }
}
