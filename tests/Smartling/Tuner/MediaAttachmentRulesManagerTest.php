<?php

namespace Smartling\Tests\Smartling\Tuner;

use PHPUnit\Framework\TestCase;
use Smartling\Helpers\GutenbergReplacementRule;
use Smartling\Tests\Mocks\WordpressFunctionsMockHelper;
use Smartling\Tuner\MediaAttachmentRulesManager;

class MediaAttachmentRulesManagerTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        WordpressFunctionsMockHelper::injectFunctionsMocks();
    }

    /**
     * @param GutenbergReplacementRule[] $allRules
     * @param GutenbergReplacementRule[] $expectedRules
     * @dataProvider getGutenbergReplacementRulesDataProvider
     */
    public function testGetGutenbergReplacementRules(array $allRules, array $expectedRules, string $blockType, string $path, string $message): void
    {
        $this->assertEquals($expectedRules, (new MediaAttachmentRulesManager($allRules))->getGutenbergReplacementRules($blockType, $path), $message);
    }

    public function getGutenbergReplacementRulesDataProvider(): array
    {
        return [
            [
                [new GutenbergReplacementRule('testBlock', 'testPath', '')],
                [new GutenbergReplacementRule('testBlock', 'testPath', '')],
                'testBlock',
                'testPath',
                'should return strict matching rules',
            ],
            [
                [
                    new GutenbergReplacementRule('test', 'test', ''),
                    new GutenbergReplacementRule('other', 'other', ''),
                ],
                [
                    new GutenbergReplacementRule('test', 'test', ''),
                ],
                'testBlock',
                'testPath',
                'should return only regex matching rules',
            ],
            [
                [
                    new GutenbergReplacementRule('te#st', 'te#st', ''),
                    new GutenbergReplacementRule('other', 'other', ''),
                ],
                [
                    new GutenbergReplacementRule('te#st', 'te#st', ''),
                ],
                'te#stBlock',
                'te#stPath',
                'should properly handle escaping',
            ],
        ];
    }
}
