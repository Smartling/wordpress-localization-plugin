<?php

namespace Smartling\Tests\Smartling\ContentTypes;

use Smartling\ContentTypes\ExternalContentElementor;
use PHPUnit\Framework\TestCase;
use Smartling\Helpers\FieldsFilterHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Submissions\SubmissionEntity;

class ExternalContentElementorTest extends TestCase {
    /**
     * @dataProvider extractElementorDataProvider
     */
    public function testExtractElementorData(string $meta, array $expected)
    {
        $proxy = $this->createMock(WordpressFunctionProxyHelper::class);
        $proxy->method('getPostMeta')->willReturn($meta);
        $this->assertEquals($expected, $this->getExternalContentElementor($proxy)->getContentFields($this->createMock(SubmissionEntity::class), false));
    }

    public function extractElementorDataProvider(): array
    {
        return [
            [
                '[]',
                [],
            ],
            [
                '[{"id":"590657a","elType":"section","settings":{"structure":"30"},"elements":[{"id":"b56da21","elType":"column","settings":{"_column_size":33,"_inline_size":null},"elements":[{"id":"c799791","elType":"widget","settings":{"editor":"<p>Left text<\/p>"},"elements":[],"widgetType":"text-editor"}],"isInner":false},{"id":"0f3ad3c","elType":"column","settings":{"_column_size":33,"_inline_size":null},"elements":[{"id":"0088b31","elType":"widget","settings":{"editor":"<p>Middle text<\/p>"},"elements":[],"widgetType":"text-editor"}],"isInner":false},{"id":"8798127","elType":"column","settings":{"_column_size":33,"_inline_size":null},"elements":[{"id":"78d53a1","elType":"widget","settings":{"title":"Right heading"},"elements":[],"widgetType":"heading"}],"isInner":false}],"isInner":false},{"id":"7a874c7","elType":"section","settings":[],"elements":[{"id":"d7d603e","elType":"column","settings":{"_column_size":100,"_inline_size":null},"elements":[{"id":"ea10188","elType":"widget","settings":{"image":{"url":"http:\/\/localhost.localdomain\/wp-content\/uploads\/2021\/09\/elementor-image.png","id":597,"alt":"","source":"library"},"image_size":"medium"},"elements":[],"widgetType":"image"}],"isInner":false}],"isInner":false}]',
                [
                    '590657a/b56da21/c799791/editor' => '<p>Left text</p>',
                    '590657a/0f3ad3c/0088b31/editor' => '<p>Middle text</p>',
                    '590657a/8798127/78d53a1/title' => 'Right heading',
                ],
            ]
        ];
    }

    public function testAlterContentFieldsForUpload()
    {
        $this->assertEquals([
            'entity' => [],
            'meta' => ['x' => 'relevant'],
        ], $this->getExternalContentElementor()->alterContentFieldsForUpload([
            'entity' => [
                'post_content' => 'irrelevant',
            ],
            'meta' => [
                'x' => 'relevant',
                '_elementor_data' => 'irrelevant',
                '_elementor_version' => 'irrelevant',
            ]
        ]));
    }

    private function getExternalContentElementor(?WordpressFunctionProxyHelper $proxy = null): ExternalContentElementor
    {
        if ($proxy === null) {
            $proxy = new WordpressFunctionProxyHelper();
        }
        $fieldsFilterHelper = $this->getMockBuilder(FieldsFilterHelper::class)->disableOriginalConstructor()->setMethodsExcept(['flattenArray'])->getMock();

        return new ExternalContentElementor($fieldsFilterHelper, $proxy);
    }

    public function testMergeElementorData()
    {
        $x = $this->getExternalContentElementor();
        $this->assertEquals(
            ['meta' => ['_elementor_data' => '[]']],
            $x->setContentFields(['meta' => ['_elementor_data' => '[]']], ['elementor' => []], $this->createMock(SubmissionEntity::class))
        );
        $original = '[{"id":"590657a","elType":"section","settings":{"structure":"30"},"elements":[{"id":"b56da21","elType":"column","settings":{"_column_size":33,"_inline_size":null},"elements":[{"id":"c799791","elType":"widget","settings":{"editor":"<p>Left text<\/p>"},"elements":[],"widgetType":"text-editor"}],"isInner":false},{"id":"0f3ad3c","elType":"column","settings":{"_column_size":33,"_inline_size":null},"elements":[{"id":"0088b31","elType":"widget","settings":{"editor":"<p>Middle text<\/p>"},"elements":[],"widgetType":"text-editor"}],"isInner":false},{"id":"8798127","elType":"column","settings":{"_column_size":33,"_inline_size":null},"elements":[{"id":"78d53a1","elType":"widget","settings":{"title":"Right heading"},"elements":[],"widgetType":"heading"}],"isInner":false}],"isInner":false},{"id":"7a874c7","elType":"section","settings":[],"elements":[{"id":"d7d603e","elType":"column","settings":{"_column_size":100,"_inline_size":null},"elements":[{"id":"ea10188","elType":"widget","settings":{"image":{"url":"http:\/\/localhost.localdomain\/wp-content\/uploads\/2021\/09\/elementor-image.png","id":597,"alt":"","source":"library"},"image_size":"medium"},"elements":[],"widgetType":"image"}],"isInner":false}],"isInner":false}]';
        $expected = addslashes(str_replace(
            ['<p>Left text<\/p>', '<p>Middle text<\/p>', 'Right heading'],
            ['<p>Left text translated<\/p>', '<p>Middle text translated<\/p>', 'Right heading translated'],
            $original
        ));

        $this->assertEquals(
            ['meta' => ['_elementor_data' => $expected]],
            $x->setContentFields(['meta' => ['_elementor_data' => $original]], ['elementor' => [
            '590657a/b56da21/c799791/editor' => '<p>Left text translated</p>',
            '590657a/0f3ad3c/0088b31/editor' => '<p>Middle text translated</p>',
            '590657a/8798127/78d53a1/title' => 'Right heading translated',
        ]], $this->createMock(SubmissionEntity::class)));
    }
}
