<?php

namespace Smartling\Tests\Smartling\Helpers;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Smartling\Extensions\Acf\AcfDynamicSupport;
use Smartling\Helpers\ContentSerializationHelper;
use Smartling\Helpers\FieldsFilterHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Tests\Traits\InvokeMethodTrait;
use Smartling\Tests\Traits\SettingsManagerMock;

class FieldsFilterHelperTest extends TestCase
{
    use InvokeMethodTrait;
    use SettingsManagerMock;

    /**
     * @dataProvider structurizeArrayDataProvider
     */
    public function testStructurizeArray(array $flat, array $structured)
    {
        $this->assertEquals($structured, $this->getFieldsFilterHelper()->structurizeArray($flat));
    }

    /**
     * @return array
     */
    public function structurizeArrayDataProvider()
    {
        $fields = [
            'a'                                                                   => ['a' => 'test'],
            ''                                                                    => ['' => 'test'],
            'a\\b'                                                                => ['a\\b' => 'test'],
            'b/'                                                                  => ['b' => ['' => 'test']],
            'entity/post_title'                                                   => ['entity' => ['post_title' => 'test']],
            'meta/_elementor_data/32f683b/8f903f2/03d5534/headline'               => ['meta' => ['_elementor_data' => ['32f683b' => ['8f903f2' => ['03d5534' => ['headline' => 'test']]]]]],
            'meta/_elementor_data/32f683b/8f903f2/background_background'          => ['meta' => ['_elementor_data' => ['32f683b' => ['8f903f2' => ['background_background' => 'test']]]]],
            'meta/_elementor_data/32f683b/8f903f2/background_color'               => ['meta' => ['_elementor_data' => ['32f683b' => ['8f903f2' => ['background_color' => 'test']]]]],
            'meta/_elementor_data/32f683b/background_background'                  => ['meta' => ['_elementor_data' => ['32f683b' => ['background_background' => 'test']]]],
            'meta/_elementor_data/32f683b/background_color'                       => ['meta' => ['_elementor_data' => ['32f683b' => ['background_color' => 'test']]]],
            'meta/_elementor_data/32f683b/background_overlay_color'               => ['meta' => ['_elementor_data' => ['32f683b' => ['background_overlay_color' => 'test']]]],
            'meta/_elementor_data/4fcce1c/d318a2e/fd63350/html'                   => ['meta' => ['_elementor_data' => ['4fcce1c' => ['d318a2e' => ['fd63350' => ['html' => 'test']]]]]],
            'meta/_elementor_data/4fcce1c/stretch_section'                        => ['meta' => ['_elementor_data' => ['4fcce1c' => ['stretch_section' => 'test']]]],
            'meta/_elementor_data/4fcce1c/layout'                                 => ['meta' => ['_elementor_data' => ['4fcce1c' => ['layout' => 'test']]]],
            'meta/_elementor_data/4fcce1c/content_position'                       => ['meta' => ['_elementor_data' => ['4fcce1c' => ['content_position' => 'test']]]],
            'meta/_elementor_data/95d5ee5/8357f39/98c52ae/html'                   => ['meta' => ['_elementor_data' => ['95d5ee5' => ['8357f39' => ['98c52ae' => ['html' => 'test']]]]]],
            'meta/_elementor_data/95d5ee5/2ab56bd/8407d1e/html'                   => ['meta' => ['_elementor_data' => ['95d5ee5' => ['2ab56bd' => ['8407d1e' => ['html' => 'test']]]]]],
            'meta/_elementor_data/95d5ee5/2ab56bd/content_position'               => ['meta' => ['_elementor_data' => ['95d5ee5' => ['2ab56bd' => ['content_position' => 'test']]]]],
            'meta/_elementor_data/95d5ee5/2ab56bd/background_background'          => ['meta' => ['_elementor_data' => ['95d5ee5' => ['2ab56bd' => ['background_background' => 'test']]]]],
            'meta/_elementor_data/95d5ee5/2ab56bd/margin/top'                     => ['meta' => ['_elementor_data' => ['95d5ee5' => ['2ab56bd' => ['margin' => ['top' => 'test']]]]]],
            'meta/_elementor_data/95d5ee5/background_background'                  => ['meta' => ['_elementor_data' => ['95d5ee5' => ['background_background' => 'test']]]],
            'meta/_elementor_data/95d5ee5/background_position'                    => ['meta' => ['_elementor_data' => ['95d5ee5' => ['background_position' => 'test']]]],
            'meta/_elementor_data/95d5ee5/background_repeat'                      => ['meta' => ['_elementor_data' => ['95d5ee5' => ['background_repeat' => 'test']]]],
            'meta/_elementor_data/cbf185e/da5adc7/4ec46b4/html'                   => ['meta' => ['_elementor_data' => ['cbf185e' => ['da5adc7' => ['4ec46b4' => ['html' => 'test']]]]]],
            'meta/_elementor_data/cbf185e/da5adc7/c74532e/cards/6a13a34/headline' => ['meta' => ['_elementor_data' => ['cbf185e' => ['da5adc7' => ['c74532e' => ['cards' => ['6a13a34' => ['headline' => 'test']]]]]]]],
            'meta/_elementor_data/cbf185e/da5adc7/c74532e/cards/6a13a34/textarea' => ['meta' => ['_elementor_data' => ['cbf185e' => ['da5adc7' => ['c74532e' => ['cards' => ['6a13a34' => ['textarea' => 'test']]]]]]]],
            'meta/_elementor_data/cbf185e/da5adc7/c74532e/cards/0e96409/headline' => ['meta' => ['_elementor_data' => ['cbf185e' => ['da5adc7' => ['c74532e' => ['cards' => ['0e96409' => ['headline' => 'test']]]]]]]],
            'meta/_elementor_data/cbf185e/da5adc7/c74532e/cards/0e96409/textarea' => ['meta' => ['_elementor_data' => ['cbf185e' => ['da5adc7' => ['c74532e' => ['cards' => ['0e96409' => ['textarea' => 'test']]]]]]]],
            'meta/_elementor_data/cbf185e/da5adc7/c74532e/cards/a2a6d21/headline' => ['meta' => ['_elementor_data' => ['cbf185e' => ['da5adc7' => ['c74532e' => ['cards' => ['a2a6d21' => ['headline' => 'test']]]]]]]],
            'meta/_elementor_data/cbf185e/da5adc7/c74532e/cards/a2a6d21/textarea' => ['meta' => ['_elementor_data' => ['cbf185e' => ['da5adc7' => ['c74532e' => ['cards' => ['a2a6d21' => ['textarea' => 'test']]]]]]]],
            'meta/_elementor_data/cbf185e/layout'                                 => ['meta' => ['_elementor_data' => ['cbf185e' => ['layout' => 'test']]]],
            'meta/_elementor_data/cbf185e/text_align'                             => ['meta' => ['_elementor_data' => ['cbf185e' => ['text_align' => 'test']]]],
            'meta/_elementor_data/0787794/74c2f0b/ce0c1f4/html'                   => ['meta' => ['_elementor_data' => ['0787794' => ['74c2f0b' => ['ce0c1f4' => ['html' => 'test']]]]]],
            'meta/_elementor_data/0787794/74c2f0b/ea86271/cards/6c7af04/headline' => ['meta' => ['_elementor_data' => ['0787794' => ['74c2f0b' => ['ea86271' => ['cards' => ['6c7af04' => ['headline' => 'test']]]]]]]],
            'meta/_elementor_data/0787794/74c2f0b/ea86271/cards/6c7af04/textarea' => ['meta' => ['_elementor_data' => ['0787794' => ['74c2f0b' => ['ea86271' => ['cards' => ['6c7af04' => ['textarea' => 'test']]]]]]]],
            'meta/_elementor_data/0787794/74c2f0b/ea86271/cards/33fbd58/headline' => ['meta' => ['_elementor_data' => ['0787794' => ['74c2f0b' => ['ea86271' => ['cards' => ['33fbd58' => ['headline' => 'test']]]]]]]],
            'meta/_elementor_data/0787794/74c2f0b/ea86271/cards/33fbd58/textarea' => ['meta' => ['_elementor_data' => ['0787794' => ['74c2f0b' => ['ea86271' => ['cards' => ['33fbd58' => ['textarea' => 'test']]]]]]]],
            'meta/_elementor_data/0787794/74c2f0b/ea86271/cards/b838dff/headline' => ['meta' => ['_elementor_data' => ['0787794' => ['74c2f0b' => ['ea86271' => ['cards' => ['b838dff' => ['headline' => 'test']]]]]]]],
            'meta/_elementor_data/0787794/74c2f0b/ea86271/cards/b838dff/textarea' => ['meta' => ['_elementor_data' => ['0787794' => ['74c2f0b' => ['ea86271' => ['cards' => ['b838dff' => ['textarea' => 'test']]]]]]]],
            'meta/_elementor_data/0787794/74c2f0b/ea86271/cards/fad4d29/headline' => ['meta' => ['_elementor_data' => ['0787794' => ['74c2f0b' => ['ea86271' => ['cards' => ['fad4d29' => ['headline' => 'test']]]]]]]],
            'meta/_elementor_data/0787794/74c2f0b/ea86271/cards/fad4d29/textarea' => ['meta' => ['_elementor_data' => ['0787794' => ['74c2f0b' => ['ea86271' => ['cards' => ['fad4d29' => ['textarea' => 'test']]]]]]]],
            'meta/_elementor_data/0787794/layout'                                 => ['meta' => ['_elementor_data' => ['0787794' => ['layout' => 'test']]]],
            'meta/_elementor_data/0787794/text_align'                             => ['meta' => ['_elementor_data' => ['0787794' => ['text_align' => 'test']]]],
            'meta/_elementor_data/83a615e/9e0bf17/0783efc/headline'               => ['meta' => ['_elementor_data' => ['83a615e' => ['9e0bf17' => ['0783efc' => ['headline' => 'test']]]]]],
            'meta/_elementor_data/83a615e/9e0bf17/0783efc/button_text'            => ['meta' => ['_elementor_data' => ['83a615e' => ['9e0bf17' => ['0783efc' => ['button_text' => 'test']]]]]],
            'meta/_yoast_wpseo_title'                                             => ['meta' => ['_yoast_wpseo_title' => 'test']],
            'meta/_yoast_wpseo_metadesc'                                          => ['meta' => ['_yoast_wpseo_metadesc' => 'test']],
            'meta/_elementor_css/status'                                          => ['meta' => ['_elementor_css' => ['status' => 'test']]],

        ];

        $data = [];

        // testing simple variants
        foreach ($fields as $source => $expected) {
            $data[] = [
                [
                    $source => 'test',
                ],
                $expected,
            ];
        }

        // testing complex structure
        $complex = $fields;
        $complexStructure = [];
        foreach ($complex as &$expected) {
            $complexStructure = array_merge_recursive($complexStructure, $expected);
            $expected = 'test';

        }

        return array_merge($data, [[$complex, $complexStructure]]);
    }

    public function testRemoveFields()
    {
        $x = $this->getFieldsFilterHelper();
        $fields = [
            'meta/stays' => 'value',
            'meta/tool_templates_1000_stays' => 'value',
            'meta/tool_templates_0_stays' => 'value',
        ];

        $this->assertEquals($fields, $x->removeFields($fields, ['tool_templates'], false));

        $this->assertEquals(['meta/stays' => 'value'], $x->removeFields($fields, ['tool_templates'], true));
    }

    public function testRemoveFieldsRegex()
    {
        $x = $this->getFieldsFilterHelper();

        $fields = ['meta/stays' => 'value'];
        for ($i = 0; $i < 301; $i++) {
            $fields["meta/tool_templates_{$i}_id"] = 'value';
            $fields["meta/tool_templates_{$i}_category"] = 'value';
            $fields["meta/tool_templates_{$i}_thumbnail_url"] = 'value';
            $fields["meta/tool_templates_{$i}_section"] = 'value';
        }
        $fields['meta/tool_templates_1000_category'] = 'value';
        $fields['meta/tool_templates_0_stays'] = 'value';

        $this->assertEquals(
            [
                'meta/stays' => 'value',
                'meta/tool_templates_1000_category' => 'value',
                'meta/tool_templates_0_stays' => 'value',
            ],
            $x->removeFields(
                $fields,
                [
                    'tool_templates_\d{1,3}_id',
                    'tool_templates_\d{1,3}_category',
                    'tool_templates_\d{1,3}_thumbnail_url',
                    'tool_templates_\d{1,3}_section'
                ],
                true
            ),
            'Should remove fields that match regex list, fields that don\'t match should remain'
        );

        $this->assertEquals(
            [
                'meta/stays' => 'value',
            ],
            $x->removeFields($fields, ['tool_templates_'], true),
            'Should remove fields even on partial regex match'
        );
    }

    public function testRemoveFieldsRegexEmpty()
    {
        $fields = ['meta/stays' => 'value'];
        $this->assertEquals(
            $fields,
            $this->getFieldsFilterHelper()->removeFields($fields, [], true),
            'Should not remove any fields on empty regex list'
        );
    }

    public function testRemoveFieldsEmptyFields()
    {
        $this->assertEquals(
            [],
            $this->getFieldsFilterHelper()->removeFields([], ['irrelevant'], true),
            'Should return empty list on empty fields list'
        );
    }

    private function getAcfDynamicSupportMock():AcfDynamicSupport|MockObject
    {
        return $this->getMockBuilder(AcfDynamicSupport::class)->disableOriginalConstructor()->getMock();
    }

    private function getFieldsFilterHelper(): FieldsFilterHelper
    {
        return new FieldsFilterHelper(
            $this->getAcfDynamicSupportMock(),
            $this->createMock(ContentSerializationHelper::class),
            $this->getSettingsManagerMock(),
            $this->createMock(WordpressFunctionProxyHelper::class),
        );
    }
}
