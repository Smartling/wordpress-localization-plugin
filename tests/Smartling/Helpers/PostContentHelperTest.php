<?php

namespace Smartling\Tests\Smartling\Helpers;

use Smartling\Helpers\GutenbergBlockHelper;
use Smartling\Helpers\PostContentHelper;
use PHPUnit\Framework\TestCase;
use Smartling\Models\GutenbergBlock;

require __DIR__ . '/../../wordpressBlocks.php';

class PostContentHelperTest extends TestCase {
    public function testApplyTranslationWithLockedBlocks()
    {
        $blockHelper = $this->createMock(GutenbergBlockHelper::class);
        $blockHelper->method('parseBlocks')->willReturnCallback(function ($string) {
            return $this->toGutenbergBlocks((new \WP_Block_Parser())->parse($string));
        });

        $x = new PostContentHelper($blockHelper);
        $this->assertStringEqualsFile(
            __DIR__ . '/Resources/WP733_expected.html',
            $x->applyContentWithBlockLocks(
                file_get_contents(__DIR__ . '/Resources/WP733_target.html'),
                file_get_contents(__DIR__ . '/Resources/WP733_translation.html'),
            )
        );
    }

    /**
     * @return GutenbergBlock[]
     */
    private function getBlocksFromFile(string $path): array
    {
        return array_map(static function (array $array) {
            return GutenbergBlock::fromArray($array);
        }, json_decode(file_get_contents($path), true));
    }

    public function testSetBlockByPath()
    {
        $x = new PostContentHelper($this->createMock(GutenbergBlockHelper::class));
        $innerAttributes = ['attribute' => 'value', PostContentHelper::SMARTLING_LOCK_ID => 'innerBlock'];
        $innerBlock = new GutenbergBlock('inner', $innerAttributes, [], '', []);
        $blocks = [
            new GutenbergBlock('test', [PostContentHelper::SMARTLING_LOCK_ID => 'parentBlock'], [$innerBlock], '', []),
        ];
        $innerAttributes['attribute'] = 'changed';
        $result = $x->setBlockByPath($blocks, 'parentBlock/innerBlock', new GutenbergBlock('inner', $innerAttributes, [], '', []));
        $this->assertEquals('changed', $result[0]->getInnerBlocks()[0]->getAttributes()['attribute']);

        $this->expectExceptionObject(new \RuntimeException('Unable to get block by path innerBlock'));
        $x->setBlockByPath($blocks, 'innerBlock', new GutenbergBlock('inner', $innerAttributes, [], '', []));
    }

    private function toGutenbergBlocks(array $blocks): array
    {
        foreach ($blocks as &$block) {
            $block = GutenbergBlock::fromArray($block);
        }
        return $blocks;
    }
}
