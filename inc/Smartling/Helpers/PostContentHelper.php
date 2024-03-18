<?php

namespace Smartling\Helpers;

use JetBrains\PhpStorm\ArrayShape;
use Smartling\Models\GutenbergBlock;

class PostContentHelper
{
    use LoggerSafeTrait;

    private const NESTED_ATTRIBUTE_SEPARATOR = '.';
    public const SMARTLING_LOCK_ID = 'smartlingLockId';
    public const SMARTLING_LOCKED = 'smartlingLocked';
    public const SMARTLING_LOCKED_ATTRIBUTES = 'smartlingLockedAttributes';

    public function __construct(private GutenbergBlockHelper $blockHelper)
    {
    }

    public function applyContentWithBlockLocks(string $target, string $content): string
    {
        $lockInfo = $this->getLockInfoFromContentString($target);
        if (count($lockInfo['lockedBlocks']) === 0 && count($lockInfo['lockedBlockAttributes']) === 0) {
            return $content;
        }
        $result = '';
        $targetBlocks = $this->blockHelper->parseBlocks($target);
        $resultBlocks = $this->blockHelper->parseBlocks($content);

        foreach ($lockInfo['lockedBlockAttributes'] as $path) {
            $parts = explode('/', $path);
            $attribute = array_pop($parts);
            $path = implode('/', $parts);
            $resultBlock = $this->getBlockByPath($resultBlocks, $path);
            if ($resultBlock === null) {
                $this->getLogger()->debug("No source block found for path=$path while processing locked attributes");
            } else {
                $targetBlock = $this->getBlockByPath($targetBlocks, $path);
                assert($targetBlock !== null);
                $attributes = $resultBlock->getAttributes();
                $targetValue = $this->getNestedAttributeValue($targetBlock->getAttributes(), $attribute);
                if (!empty($targetValue)) {
                    $this->setNestedAttributeValue($attributes, $attribute, $targetValue);
                }
                $resultBlocks = $this->setBlockByPath($resultBlocks, $path, $resultBlock->withAttributes($attributes));
            }
        }

        foreach ($lockInfo['lockedBlocks'] as $path) {
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

    private function getNestedAttributeValue(array $array, array|string $parents): mixed
    {
        if (!is_array($parents)) {
            $parents = explode(self::NESTED_ATTRIBUTE_SEPARATOR, $parents);
        }

        $pointer = &$array;
        foreach ($parents as $parent) {
            if (is_array($pointer) && array_key_exists($parent, $pointer)) {
                $pointer = &$pointer[$parent];
            } else {
                return null;
            }
        }

        return $pointer;
    }

    private function setNestedAttributeValue(array &$array, array|string $parents, mixed $value): void
    {
        if (!is_array($parents)) {
            $parents = explode(self::NESTED_ATTRIBUTE_SEPARATOR, $parents);
        }

        $pointer = &$array;
        foreach ($parents as $parent) {
            if (isset($pointer) && !is_array($pointer)) {
                $pointer = [];
            }

            $pointer = &$pointer[$parent];
        }

        $pointer = $value;
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

                $replacement = $this->replaceInnerBlock($block, implode('/', $parts), $replace);

                return $replacement ? $parent->withInnerBlock($replacement, $index) : $parent;
            }
        }

        return null;
    }

    public function replacePostTranslate(string $original, string $translated): string
    {
        return $this->blockHelper->replacePostTranslateBlockContent($original, $translated);
    }

    #[ArrayShape(['lockedBlocks' => 'string[]', 'lockedBlockAttributes' => 'string[]'])]
    private function getLockInfoFromContentString(string $content): array
    {
        return $this->getLockInfoFromBlocksArray($this->blockHelper->parseBlocks($content));
    }

    /**
     * @param GutenbergBlock[] $blocks
     */
    #[ArrayShape(['lockedBlocks' => 'string[]', 'lockedBlockAttributes' => 'string[]'])]
    private function getLockInfoFromBlocksArray(array $blocks, string $prefix = ''): array
    {
        $lockedBlockAttributes = [];
        $lockedBlocks = [];
        foreach ($blocks as $block) {
            $lockInfo = $this->getLockInfoFromBlocksArray($block->getInnerBlocks(), "$prefix{$block->getSmartlingLockId()}/");
            if (count($lockInfo['lockedBlockAttributes']) > 0) {
                $lockedBlockAttributes[] = $lockInfo['lockedBlockAttributes'];
            }
            if (count($lockInfo['lockedBlocks']) > 0) {
                $lockedBlocks[] = $lockInfo['lockedBlocks'];
            }
            $lockId = $block->getSmartlingLockId();
            if ($lockId !== null) {
                $path = $prefix . $lockId;
                if ($block->isSmartlingLocked()) {
                    $lockedBlocks[] = [$path];
                }
                foreach ($block->getSmartlingLockedAttributes() as $attribute) {
                    $lockedBlockAttributes[] = ["$path/$attribute"];
                }
            }
        }

        return [
            'lockedBlocks' => array_merge(...$lockedBlocks),
            'lockedBlockAttributes' => array_merge(...$lockedBlockAttributes),
        ];
    }

    /**
     * @param GutenbergBlock[] $blocks
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

    public function setBlockByPath(array $blocks, string $path, GutenbergBlock $gutenbergBlock): array
    {
        $result = $this->setBlock($blocks, $path, $gutenbergBlock);

        if ($result['useInnerBlock']) {
            return $result['block']->getInnerBlocks();
        }

        $blocks[$result['index']] = $result['block'];

        return $blocks;
    }

    #[ArrayShape(['block' => GutenbergBlock::class, 'index' => '?integer', 'useInnerBlock' => 'boolean'])]
    private function setBlock(array $blocks, string $path, GutenbergBlock $gutenbergBlock): array
    {
        $replaced = null;
        $parts = explode('/', $path);
        $lockId = array_shift($parts);
        foreach ($blocks as $index => &$block) {
            if (!$block instanceof GutenbergBlock) {
                throw new \RuntimeException('Blocks expected to be array of GutenbergBlocks, got ' . gettype($block));
            }
            if ($block->getSmartlingLockId() === $lockId) {
                $replaced = $index;
                if (count($parts) === 0) {
                    return ['block' => $gutenbergBlock, 'index' => $index, 'useInnerBlock' => false];
                }
                $blockInfo = $this->setBlock($block->getInnerBlocks(), implode('/', $parts), $gutenbergBlock);
                $replacement = $blockInfo['useInnerBlock'] ? $blockInfo['block']->getInnerBlocks()[$blockInfo['index']] : $blockInfo['block'];
                $block = $block->withInnerBlock($replacement, $blockInfo['index']);
            }
        }
        unset($block);
        if ($replaced === null) {
            throw new \RuntimeException('Unable to get block by path ' . $path);
        }
        return ['block' => new GutenbergBlock(null, [], $blocks, '', []), 'index' => $replaced, 'useInnerBlock' => true];
    }
}
