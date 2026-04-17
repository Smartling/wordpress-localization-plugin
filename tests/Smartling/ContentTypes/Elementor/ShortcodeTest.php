<?php

namespace Smartling\ContentTypes\Elementor;

use PHPUnit\Framework\TestCase;
use Smartling\ContentTypes\Elementor\Elements\Shortcode;
use Smartling\ContentTypes\ExternalContentElementor;
use Smartling\Models\RelatedContentInfo;
use Smartling\Submissions\SubmissionEntity;

class ShortcodeTest extends TestCase
{
    private function makeWidget(array $settings = []): Shortcode
    {
        return new Shortcode([
            'id' => 'abc123',
            'elType' => 'widget',
            'widgetType' => 'shortcode',
            'settings' => $settings,
            'elements' => [],
        ]);
    }

    public function testGetType(): void
    {
        $this->assertEquals('shortcode', $this->makeWidget()->getType());
    }

    public function testGetTranslatableStrings(): void
    {
        $shortcodeValue = '[contact-form-7 id="123" title="Contact form 1"]';
        $strings = $this->makeWidget(['shortcode' => $shortcodeValue])->getTranslatableStrings();

        $this->assertEquals($shortcodeValue, $strings['abc123']['shortcode']);
    }

    public function testGetTranslatableStringsEmpty(): void
    {
        $strings = $this->makeWidget()->getTranslatableStrings();

        $this->assertEquals([], $strings['abc123']);
    }

    public function testSetTargetContent(): void
    {
        $translatedShortcode = '[contact-form-7 id="123" title="Formulario de contacto 1"]';

        $result = $this->makeWidget(['shortcode' => '[contact-form-7 id="123" title="Contact form 1"]'])
            ->setTargetContent(
                $this->createMock(ExternalContentElementor::class),
                new RelatedContentInfo([]),
                ['abc123' => ['shortcode' => $translatedShortcode]],
                $this->createMock(SubmissionEntity::class),
            )->toArray();

        $this->assertEquals($translatedShortcode, $result['settings']['shortcode']);
    }
}
