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
        $originalBlocksFromFile = $this->getBlocksFromFile(__DIR__ . '/Resources/WP733_original_blocks.json');
        $blockHelper->method('parseBlocks')->willReturn(
            $originalBlocksFromFile,
            $originalBlocksFromFile,
            $this->getBlocksFromFile(__DIR__ . '/Resources/WP733_translated_blocks.json'),
        );

        $x = new PostContentHelper($blockHelper);
        $this->assertStringEqualsFile(
            __DIR__ . '/Resources/WP733_expected.html',
            $x->applyTranslationsWithLockedBlocks(
                file_get_contents(__DIR__ . '/Resources/WP733_original.html'),
                file_get_contents(__DIR__ . '/Resources/WP733_translated.html'),
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
}
