<?php

namespace Smartling\Helpers;

use Smartling\Models\GutenbergBlock;
use Smartling\MonologWrapper\MonologWrapper;
use Smartling\Vendor\Psr\Log\LoggerInterface;

class PostContentHelper
{
    public const SMARTLING_LOCK_ID = 'smartlingLockId';
    public const SMARTLING_LOCKED = 'smartlingLocked';

    private GutenbergBlockHelper $blockHelper;
    private LoggerInterface $logger;

    public function __construct(GutenbergBlockHelper $blockHelper)
    {
        $this->blockHelper = $blockHelper;
        $this->logger = MonologWrapper::getLogger();
    }

    public function applyContentWithLockedBlocks(string $target, string $content): string
    {
        $blockPaths = $this->getLockedBlockPathsFromContentString($target);
        if (count($blockPaths) === 0) {
            return $content;
        }
        $result = '';
        $targetBlocks = $this->blockHelper->parseBlocks($target);
        $resultBlocks = $this->blockHelper->parseBlocks($content);

        foreach ($blockPaths as $path) {
            $lockedContent = $this->getBlockByPath($targetBlocks, $path);
            if ($lockedContent !== null) {
                $parts = explode('/', $path);
                $lockId = array_shift($parts);
                foreach ($resultBlocks as &$block) {
                    if ($block->getSmartlingLockId() === $lockId) {
                        if (count($parts) === 0) {
                            $block = $lockedContent;
                            break;
                        }

                        $replacedContent = $this->replaceInnerBlock($block, implode('/', $parts), $lockedContent);
                        if ($replacedContent !== null) {
                            $block = $replacedContent;
                        }
                        break;
                    }
                }
            }
            unset($block);
        }

        foreach ($resultBlocks as $block) {
            $result .= serialize_block($block->toArray());
        }

        return $result;
    }

    private function replaceInnerBlock(GutenbergBlock $parent, string $path, GutenbergBlock $replace): ?GutenbergBlock
    {
        $parts = explode('/', $path);
        $lockId = array_shift($parts);
        foreach ($parent->getInnerBlocks() as $index => $block) {
            if ($block->getSmartlingLockId() === $lockId) {
                if (count($parts) === 0) {
                    return $parent->withInnerBlock($replace, $index);
                }

                return $parent->withInnerBlock($this->replaceInnerBlock($block, implode('/', $parts), $replace), $index);
            }
        }

        return null;
    }

    public function replacePostTranslate(string $original, string $translated): string
    {
        return $this->blockHelper->replacePostTranslateBlockContent($original, $translated);
    }

    /**
     * @return string[]
     */
    public function getLockedBlockPathsFromContentString(string $content): array
    {
        return $this->getLockedBlockPathsFromBlocksArray($this->blockHelper->parseBlocks($content));
    }

    /**
     * @param GutenbergBlock[] $blocks
     * @param string $prefix
     * @return string[]
     */
    private function getLockedBlockPathsFromBlocksArray(array $blocks, string $prefix = ''): array
    {
        $result = [];
        foreach ($blocks as $block) {
            foreach ($this->getLockedBlockPathsFromBlocksArray($block->getInnerBlocks(), "$prefix{$block->getSmartlingLockId()}/") as $lockedBlock) {
                $result[] = $lockedBlock;
            }
            $attributes = $block->getAttributes();
            if (array_key_exists(self::SMARTLING_LOCKED, $attributes) &&
                array_key_exists(self::SMARTLING_LOCK_ID, $attributes) &&
                $attributes[self::SMARTLING_LOCKED] === true) {
                $result[] = $prefix . $attributes[self::SMARTLING_LOCK_ID];
            }
        }

        return $result;
    }

    /**
     * @param GutenbergBlock[] $blocks
     * @return GutenbergBlock|null
     */
    private function getBlockByPath(array $blocks, string $path): ?GutenbergBlock
    {
        $parts = explode('/', $path);
        $lockId = array_shift($parts);
        foreach ($blocks as $block) {
            if ($block->getSmartlingLockId() === $lockId) {
                if (count($parts) === 0) {
                    return $block;
                }
                return $this->getBlockByPath($block->getInnerBlocks(), implode('/', $parts));
            }
        }
        return null;
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
