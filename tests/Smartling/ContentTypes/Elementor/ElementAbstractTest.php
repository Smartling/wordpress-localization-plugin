<?php

namespace Smartling\ContentTypes\Elementor;

use PHPUnit\Framework\TestCase;
use Smartling\ContentTypes\ExternalContentElementor;
use Smartling\ContentTypes\Elementor\Elements\Unknown;
use Smartling\Models\RelatedContentInfo;
use Smartling\Submissions\SubmissionEntity;

class TestableElementAbstract extends Unknown
{
    public function publicGetIntSettingByKey(string $key, array $settings): ?int
    {
        return $this->getIntSettingByKey($key, $settings);
    }
}

class ElementAbstractTest extends TestCase
{
    /**
     * @dataProvider settingsProvider
     */
    public function testGetIntSettingByKey(string $key, array $settings, ?int $expected): void
    {
        $element = new TestableElementAbstract(['settings' => $settings]);
        $this->assertEquals($expected, $element->publicGetIntSettingByKey($key, $settings));
    }

    public function settingsProvider(): array
    {
        return [
            ['test', ['test' => 1], 1],
            ['test', ['test' => 1.1], null],
            ['test', ['test' => '1'], 1],
            ['test', ['test' => '1.1'], null],
            ['test', ['test' => 'non_integer'], null],
            ['test', [], null],
            ['test/test', ['test' => ['test' => 1]], 1],
            ['test/test', ['test' => ['test' => '1']], 1],
            ['test/test', ['test' => ['test' => 'non_integer']], null],
            ['test/test', ['test' => []], null],
            ['test/test', [], null],
        ];
    }

    public function testSetTargetContentWithDynamicSettingsAndElementorTags(): void
    {
        $element = new TestableElementAbstract([
            'id' => 'test-id',
            'settings' => [
                '__dynamic__' => [
                    'image' => '[elementor-tag id="" name="post-featured-image" settings="%7B%22fallback%22%3A%7B%22url%22%3A%22http%3A%2F%2Fexample.com%2Fwp-content%2Fuploads%2F2025%2F02%2FPlaceholder.webp%22%2C%22id%22%3A13574%2C%22size%22%3A%22%22%2C%22alt%22%3A%22%22%2C%22source%22%3A%22library%22%7D%7D"]',
                ],
                'title' => 'Original Title',
            ]
        ]);

        $externalContentElementor = $this->createMock(ExternalContentElementor::class);
        $targetId = 13;
        $externalContentElementor->method('getTargetId')->willReturn($targetId);

        $result = $element->setTargetContent(
            $externalContentElementor,
            new RelatedContentInfo([]),
            ['test-id' => ['title' => 'Translated Title']],
            $this->createMock(SubmissionEntity::class),
        )->toArray();

        $this->assertEquals('Translated Title', $result['settings']['title']);
        $this->assertEquals('[elementor-tag id="" name="post-featured-image" settings="%7B%22fallback%22%3A%7B%22url%22%3A%22http%3A%5C%2F%5C%2Fexample.com%5C%2Fwp-content%5C%2Fuploads%5C%2F2025%5C%2F02%5C%2FPlaceholder.webp%22%2C%22id%22%3A' . $targetId . '%2C%22size%22%3A%22%22%2C%22alt%22%3A%22%22%2C%22source%22%3A%22library%22%7D%7D"]', $result['settings']['__dynamic__']['image']);
    }
}
