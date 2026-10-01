<?php

namespace Smartling\Tests\Smartling\Tuner;

use PHPUnit\Framework\TestCase;
use Smartling\Tuner\JsonFieldRule;
use Smartling\Tuner\JsonLeafMatcher;

class JsonLeafMatcherTest extends TestCase
{
    private const P = 'accordions__sovos-accordions__items__sovos-accordion__';

    /**
     * Trimmed copy of the sovos-overview-hero widget structure, including the stale placeholder data
     */
    public static function sovosData(): array
    {
        $p = self::P;
        $label = static fn(string $text): array => ['text' => $text, 'html_tag' => 'div'];
        $listItem = static fn(string $id, string $text): array => [
            '_id' => $id,
            $p . 'content__sovos-list-items__items__sovos-list-item__text' => ['text' => $text, 'html_tag' => 'div'],
        ];
        return [[
            'id' => 's1', 'elType' => 'section', 'elements' => [[
                'id' => 'c1', 'elType' => 'column', 'elements' => [[
                    'id' => 'w1', 'elType' => 'widget', 'widgetType' => 'sovos-overview-hero', 'elements' => [],
                    'settings' => [
                        'title' => ['text' => 'Sovi AI', 'html_tag' => 'div'],
                        'image' => ['id' => 269318, 'alt' => ''],
                        'accordions_repeater' => [[
                            'accordions__sovos-accordions__items_repeater' => [
                                [
                                    $p . 'label' => $label('Description'),
                                    $p . 'content_repeater' => [[
                                        'pattern_type' => 'sovos-rich-text',
                                        $p . 'content__sovos-rich-text__text' => '<p>Real body</p>',
                                        $p . 'content__sovos-list-items__items_repeater' => [
                                            $listItem('a', 'List Item 1'),
                                            $listItem('b', 'List Item 2'),
                                        ],
                                    ]],
                                ],
                                [
                                    $p . 'label' => $label('Features'),
                                    $p . 'content_repeater' => [[
                                        'pattern_type' => 'sovos-list-items',
                                        $p . 'content__sovos-rich-text__text' => '<p>Sample rich text</p>',
                                        $p . 'content__sovos-list-items__items_repeater' => [
                                            $listItem('c', 'Feature one'),
                                            $listItem('d', 'Feature two'),
                                        ],
                                    ]],
                                ],
                            ],
                        ]],
                    ],
                ], [
                    'id' => 'w2', 'elType' => 'widget', 'widgetType' => 'heading',
                    'settings' => ['title' => ['text' => 'Not in the hero']], 'elements' => [],
                ]],
            ]],
        ]];
    }

    private function values(JsonFieldRule $rule): array
    {
        return array_column((new JsonLeafMatcher())->match(self::sovosData(), $rule), 'value');
    }

    public function testKeySuffixMatchesAnywhereIgnoringArrayIndices(): void
    {
        $this->assertSame(
            ['Description', 'Features'],
            $this->values(new JsonFieldRule('m', '$..' . self::P . 'label.text', 'translate', 'sovos-overview-hero')),
        );
    }

    public function testWidgetScopeExcludesOtherWidgets(): void
    {
        $this->assertSame(['Sovi AI', 'Not in the hero'], $this->values(new JsonFieldRule('m', '$..title.text', 'translate', '', [
            ['ancestor' => 2, 'key' => 'elType', 'value' => 'widget'],
        ])));
        $this->assertSame(['Sovi AI'], $this->values(new JsonFieldRule('m', '$..title.text', 'translate', 'sovos-overview-hero')));
    }

    public function testConditionOnAncestorZeroExcludesPlaceholderRichText(): void
    {
        $rule = new JsonFieldRule('m', '$..' . self::P . 'content__sovos-rich-text__text', 'translate', 'sovos-overview-hero', [
            ['ancestor' => 0, 'key' => 'pattern_type', 'value' => 'sovos-rich-text'],
        ]);
        $this->assertSame(['<p>Real body</p>'], $this->values($rule));
    }

    public function testConditionOnHigherAncestorExcludesPlaceholderListItems(): void
    {
        $rule = new JsonFieldRule('m', '$..' . self::P . 'content__sovos-list-items__items__sovos-list-item__text.text', 'translate', 'sovos-overview-hero', [
            ['ancestor' => 2, 'key' => 'pattern_type', 'value' => 'sovos-list-items'],
        ]);
        $this->assertSame(['Feature one', 'Feature two'], $this->values($rule));
    }

    public function testMatchesNumericLeavesForRelatedRules(): void
    {
        $this->assertSame([269318], $this->values(new JsonFieldRule('m', '$..image.id', 'related|attachment', 'sovos-overview-hero')));
    }

    public function testSetValueWritesByConcreteSegmentsAndPathIsStable(): void
    {
        $matcher = new JsonLeafMatcher();
        $rule = new JsonFieldRule('m', '$..' . self::P . 'label.text', 'translate', 'sovos-overview-hero');
        $data = self::sovosData();
        $leaves = $matcher->match($data, $rule);
        $this->assertTrue($matcher->setValue($data, $leaves[1]['segments'], 'Özellikler'));
        $this->assertSame(
            ['Description', 'Özellikler'],
            array_column($matcher->match($data, $rule), 'value'),
        );
        $this->assertStringStartsWith('$[0].elements[0].elements[0].settings.accordions_repeater[0]', $matcher->pathToString($leaves[0]['segments']));
        $this->assertStringContainsString("['" . self::P . "label']", $matcher->pathToString($leaves[0]['segments']));
        $this->assertFalse($matcher->setValue($data, ['nope', 1], 'x'));
    }
}
