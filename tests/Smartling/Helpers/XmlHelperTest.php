<?php

namespace Smartling\Tests\Smartling\Helpers;

use PHPUnit\Framework\TestCase;
use Smartling\Helpers\ContentSerializationHelper;
use Smartling\Helpers\Serializers\SerializerJsonWithFallback;
use Smartling\Helpers\XmlHelper;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Tests\Mocks\WordpressFunctionsMockHelper;

class XmlHelperTest extends TestCase
{
    private const SETTINGS = [
        'ignore' => [],
        'key' => ['seo' => ['seo_field']],
        'copy' => ['name' => [], 'regexp' => []],
        ContentSerializationHelper::FILTER_FIELD_NAME_REGEXP => false,
    ];

    protected function setUp(): void
    {
        WordpressFunctionsMockHelper::injectFunctionsMocks();
    }

    /**
     * Looking the profile up for every row made XML generation cost grow with the row count on
     * multisites where each blog switch is slower than the previous one (WP-1025).
     */
    public function testXmlEncodeResolvesFilterSettingsOncePerFile(): void
    {
        $source = [];
        for ($i = 0; $i < 50; $i++) {
            $source["meta/field_$i"] = "value $i";
        }

        $contentSerializationHelper = $this->createMock(ContentSerializationHelper::class);
        $contentSerializationHelper->expects(self::once())->method('prepareFieldProcessorValues')->willReturn(self::SETTINGS);
        $settingsManager = $this->createMock(SettingsManager::class);
        $settingsManager->expects(self::once())->method('getProfileBySubmission');

        (new XmlHelper($contentSerializationHelper, new SerializerJsonWithFallback(), $settingsManager))
            ->xmlEncode($source, $this->createMock(SubmissionEntity::class));
    }

    public function testXmlEncodeOutput(): void
    {
        $contentSerializationHelper = $this->createMock(ContentSerializationHelper::class);
        $contentSerializationHelper->method('prepareFieldProcessorValues')->willReturn(self::SETTINGS);

        $xml = (new XmlHelper($contentSerializationHelper, new SerializerJsonWithFallback(), $this->createMock(SettingsManager::class)))
            ->xmlEncode(['entity/post_title' => 'Title', 'meta/seo_field' => 'Description'], $this->createMock(SubmissionEntity::class));

        self::assertStringContainsString('<string name="entity/post_title"><![CDATA[Title]]></string>', $xml);
        self::assertStringContainsString('<string name="meta/seo_field" key="seo"><![CDATA[Description]]></string>', $xml);
    }

    public function testXmlDecodeResolvesFilterSettingsOncePerFile(): void
    {
        $strings = '';
        for ($i = 0; $i < 50; $i++) {
            $strings .= "<string name=\"meta/field_$i\"><![CDATA[value $i]]></string>";
        }

        $contentSerializationHelper = $this->createMock(ContentSerializationHelper::class);
        $contentSerializationHelper->expects(self::once())->method('prepareFieldProcessorValues')->willReturn(self::SETTINGS);

        $decoded = (new XmlHelper($contentSerializationHelper, new SerializerJsonWithFallback(), $this->createMock(SettingsManager::class)))
            ->xmlDecode("<?xml version=\"1.0\" encoding=\"UTF-8\"?><data>$strings</data>", $this->createMock(SubmissionEntity::class));

        self::assertCount(50, $decoded->getTranslatedFields());
        self::assertSame('value 49', $decoded->getTranslatedFields()['meta/field_49']);
    }
}
