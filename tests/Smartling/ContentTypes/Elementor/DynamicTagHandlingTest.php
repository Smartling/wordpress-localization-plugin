<?php

namespace Smartling\Tests\Smartling\ContentTypes\Elementor;

use PHPUnit\Framework\TestCase;
use Smartling\ContentTypes\Elementor\DynamicTagManager;
use Smartling\ContentTypes\Elementor\Elements\Unknown;

class DynamicTagHandlingTest extends TestCase
{
    public function testReplaceDynamicTagSettingNoManager(): void
    {
        $element = new Unknown(['settings' => []]);
        $result = $element->replaceDynamicTagSetting('[elementor-tag]', '456');
        $this->assertEquals('[elementor-tag]', $result);
    }

    public function testGetRelatedFromDynamicNoManager(): void
    {
        $element = new Unknown(['settings' => []]);
        $related = $element->getRelated();
        $this->assertCount(0, $related->getInfo());
    }

    public function testReplaceDynamicInternalUrl(): void
    {
        $original = '[elementor-tag id="3039f16" name="internal-url" settings="%7B%22type%22%3A%22post%22%2C%22post_id%22%3A%22123%22%7D"]';
        $result = $this->getElement($this->getManager())->replaceDynamicTagSetting($original, '456');
        $this->assertEquals(str_replace('123', '456', $original), $result);
    }

    public function testReplaceDynamicPopup(): void
    {
        $original = '[elementor-tag id="71944c2" name="popup" settings="%7B%22popup%22%3A%22123%22%7D"]';
        $result = $this->getElement($this->getManager())->replaceDynamicTagSetting($original, '456');
        $this->assertEquals(str_replace('123', '456', $original), $result);
    }

    private function getManager(): DynamicTagManager
    {
        $mock = $this->createMock(DynamicTagManager::class);
        $mock->method('tag_data_to_tag_text')
            ->willReturnCallback(static function (string $tagId, string $tagName, array $settings): string {
                return sprintf(
                    '[%1$s id="%2$s" name="%3$s" settings="%4$s"]',
                    'elementor-tag',
                    $tagId,
                    $tagName,
                    urlencode(json_encode($settings, JSON_THROW_ON_ERROR | JSON_FORCE_OBJECT)),
                );
            });
        $mock->method('tag_text_to_tag_data')
            ->willReturnCallback(function (string $tagText): array {
                $parts = explode(' ', $tagText);
                $decoded = urldecode(substr(explode('=', $parts[3])[1], 1, -2));

                return [
                    'id' => substr(explode('=', $parts[1])[1], 1, -1),
                    'name' => substr(explode('=', $parts[2])[1], 1, -1),
                    'settings' => json_decode($decoded, true, flags: JSON_THROW_ON_ERROR),
                ];
            }
            );

        return $mock;
    }

    private function getElement(DynamicTagManager $mock)
    {
        return (new class($mock, ['settings' => []]) extends Unknown {
            public function __construct(private DynamicTagManager $manager, array $array = [])
            {
                parent::__construct($array);
            }

            public function getDynamicTagsManager(): DynamicTagManager
            {
                return $this->manager;
            }
        });
    }
}
