<?php

namespace Smartling\Tests\Smartling\ContentTypes;

use PHPUnit\Framework\TestCase;
use Smartling\ContentTypes\ContentTypeHelper;
use Smartling\ContentTypes\Elementor\ElementFactory4;
use Smartling\ContentTypes\ExternalContentElementor4;
use Smartling\Helpers\FieldsFilterHelper;
use Smartling\Helpers\LinkProcessor;
use Smartling\Helpers\PluginHelper;
use Smartling\Helpers\SiteHelper;
use Smartling\Helpers\UserHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

class ExternalContentElementor4Test extends TestCase
{
    private function getHandler(?WordpressFunctionProxyHelper $proxy = null): ExternalContentElementor4
    {
        $contentTypeHelper = $this->createMock(ContentTypeHelper::class);
        $contentTypeHelper->method('isPost')->willReturn(true);
        $pluginHelper = $this->createMock(PluginHelper::class);
        $pluginHelper->method('versionInRange')->willReturn(true);
        if ($proxy === null) {
            $proxy = $this->createMock(WordpressFunctionProxyHelper::class);
        }
        $submissionManager = $this->createMock(SubmissionManager::class);
        $fieldsFilterHelper = $this->getMockBuilder(FieldsFilterHelper::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        $siteHelper = $this->createPartialMock(SiteHelper::class, ['restoreBlogId', 'switchBlogId']);

        return new ExternalContentElementor4(
            $contentTypeHelper,
            new ElementFactory4(),
            $fieldsFilterHelper,
            $pluginHelper,
            $siteHelper,
            $submissionManager,
            $this->createMock(UserHelper::class),
            $proxy,
            $this->createMock(LinkProcessor::class),
        );
    }

    private function makeProxy(string $data): WordpressFunctionProxyHelper
    {
        $proxy = $this->createMock(WordpressFunctionProxyHelper::class);
        $proxy->method('getPostMeta')->willReturn($data);
        $proxy->method('get_plugins')->willReturn(['elementor/elementor.php' => ['Version' => '4.0.0']]);
        $proxy->method('is_plugin_active')->willReturn(true);
        return $proxy;
    }

    private function mockSubmission(): SubmissionEntity
    {
        $submission = $this->createMock(SubmissionEntity::class);
        $submission->method('getSourceId')->willReturn(1);
        return $submission;
    }

    public function testExtractsHeadingTitle(): void
    {
        // Strings are keyed as containerId/widgetId/settingKey after flattening
        $data = json_encode([[
            'id' => 'container1',
            'elType' => 'e-flexbox',
            'settings' => [],
            'elements' => [[
                'id' => 'heading1',
                'elType' => 'widget',
                'widgetType' => 'e-heading',
                'settings' => [
                    'title' => [
                        '$$type' => 'html-v3',
                        'value' => [
                            'content' => ['$$type' => 'string', 'value' => 'Hello World'],
                            'children' => [],
                        ],
                    ],
                ],
                'elements' => [],
                'styles' => [],
                'interactions' => [],
                'editor_settings' => [],
                'version' => '0.0',
            ]],
            'isInner' => false,
            'styles' => [],
            'interactions' => [],
            'editor_settings' => [],
            'version' => '0.0',
        ]]);

        $fields = $this->getHandler($this->makeProxy($data))->getContentFields($this->mockSubmission(), false);

        $this->assertArrayHasKey('container1/heading1/title', $fields);
        $this->assertEquals('Hello World', $fields['container1/heading1/title']);
    }

    public function testExtractsParagraphContent(): void
    {
        $data = json_encode([[
            'id' => 'container1',
            'elType' => 'e-flexbox',
            'settings' => [],
            'elements' => [[
                'id' => 'para1',
                'elType' => 'widget',
                'widgetType' => 'e-paragraph',
                'settings' => [
                    'paragraph' => [
                        '$$type' => 'html-v3',
                        'value' => [
                            'content' => ['$$type' => 'string', 'value' => 'Atomic paragraph'],
                            'children' => [],
                        ],
                    ],
                ],
                'elements' => [],
                'styles' => [],
                'interactions' => [],
                'editor_settings' => [],
                'version' => '0.0',
            ]],
            'isInner' => false,
            'styles' => [],
            'interactions' => [],
            'editor_settings' => [],
            'version' => '0.0',
        ]]);

        $fields = $this->getHandler($this->makeProxy($data))->getContentFields($this->mockSubmission(), false);
        $this->assertEquals('Atomic paragraph', $fields['container1/para1/paragraph']);
    }

    public function testExtractsButtonText(): void
    {
        $data = json_encode([[
            'id' => 'container1',
            'elType' => 'e-flexbox',
            'settings' => [],
            'elements' => [[
                'id' => 'btn1',
                'elType' => 'widget',
                'widgetType' => 'e-button',
                'settings' => [
                    'text' => ['$$type' => 'html-v3', 'value' => ['content' => ['$$type' => 'string', 'value' => 'Click me'], 'children' => []]],
                ],
                'elements' => [],
                'styles' => [],
                'interactions' => [],
                'editor_settings' => [],
                'version' => '0.0',
            ]],
            'isInner' => false,
            'styles' => [],
            'interactions' => [],
            'editor_settings' => [],
            'version' => '0.0',
        ]]);

        $fields = $this->getHandler($this->makeProxy($data))->getContentFields($this->mockSubmission(), false);
        $this->assertEquals('Click me', $fields['container1/btn1/text']);
    }

    public function testExtractsFormInputPlaceholderButNotInternalFields(): void
    {
        $data = json_encode([[
            'id' => 'form1',
            'elType' => 'e-form',
            'settings' => [],
            'elements' => [[
                'id' => 'input1',
                'elType' => 'widget',
                'widgetType' => 'e-form-input',
                'settings' => [
                    'placeholder' => ['$$type' => 'string', 'value' => 'First name'],
                    'type' => ['$$type' => 'string', 'value' => 'text'],
                    '_cssid' => ['$$type' => 'string', 'value' => 'e-form-first-name'],
                ],
                'elements' => [],
                'styles' => [],
                'interactions' => [],
                'editor_settings' => [],
                'version' => '0.0',
            ]],
            'isInner' => false,
            'styles' => [],
            'interactions' => [],
            'editor_settings' => [],
            'version' => '0.0',
        ]]);

        $fields = $this->getHandler($this->makeProxy($data))->getContentFields($this->mockSubmission(), false);
        $this->assertEquals('First name', $fields['form1/input1/placeholder']);
        $this->assertArrayNotHasKey('form1/input1/type', $fields);
        $this->assertArrayNotHasKey('form1/input1/_cssid', $fields);
    }

    public function testExtractsImageAttachmentId(): void
    {
        $data = json_encode([[
            'id' => 'container1',
            'elType' => 'e-flexbox',
            'settings' => [],
            'elements' => [[
                'id' => 'img1',
                'elType' => 'widget',
                'widgetType' => 'e-image',
                'settings' => [
                    'image' => [
                        '$$type' => 'image',
                        'value' => [
                            'src' => [
                                '$$type' => 'image-src',
                                'value' => [
                                    'id' => ['$$type' => 'image-attachment-id', 'value' => 23],
                                    'url' => null,
                                ],
                            ],
                        ],
                    ],
                ],
                'elements' => [],
                'styles' => [],
                'interactions' => [],
                'editor_settings' => [],
                'version' => '0.0',
            ]],
            'isInner' => false,
            'styles' => [],
            'interactions' => [],
            'editor_settings' => [],
            'version' => '0.0',
        ]]);

        $related = $this->getHandler($this->makeProxy($data))->getRelatedContent('post', 1);
        // getRelatedContentList() returns {contentType => [ids]}
        $this->assertArrayHasKey(ContentTypeHelper::POST_TYPE_ATTACHMENT, $related);
        $this->assertContains(23, $related[ContentTypeHelper::POST_TYPE_ATTACHMENT]);
    }

    public function testSetContentFieldsWritesTranslationBackIntoTypedStructure(): void
    {
        $elementData = [[
            'id' => 'container1',
            'elType' => 'e-flexbox',
            'settings' => [],
            'elements' => [[
                'id' => 'heading1',
                'elType' => 'widget',
                'widgetType' => 'e-heading',
                'settings' => [
                    'title' => [
                        '$$type' => 'html-v3',
                        'value' => [
                            'content' => ['$$type' => 'string', 'value' => 'Original heading'],
                            'children' => [],
                        ],
                    ],
                ],
                'elements' => [],
                'styles' => [],
                'interactions' => [],
                'editor_settings' => [],
                'version' => '0.0',
            ]],
            'isInner' => false,
            'styles' => [],
            'interactions' => [],
            'editor_settings' => [],
            'version' => '0.0',
        ]];

        $proxy = $this->makeProxy(json_encode($elementData));
        $original = ['meta' => [ExternalContentElementor4::META_FIELD_NAME => json_encode($elementData)]];

        // Translation strings are keyed as {containerId: {widgetId: {settingKey: translatedValue}}}
        $translation = [
            'meta' => [ExternalContentElementor4::META_FIELD_NAME => json_encode($elementData)],
            'elementor' => [
                'container1' => [
                    'heading1' => ['title' => 'Translated heading'],
                ],
            ],
        ];

        $submission = $this->createMock(SubmissionEntity::class);
        $submission->method('getSourceBlogId')->willReturn(1);
        $submission->method('getTargetBlogId')->willReturn(2);
        $submission->method('getSourceId')->willReturn(10);

        $result = $this->getHandler($proxy)->setContentFields($original, $translation, $submission);
        $resultData = json_decode($result['meta'][ExternalContentElementor4::META_FIELD_NAME], true);

        $headingSettings = $resultData[0]['elements'][0]['settings'];
        $this->assertEquals('html-v3', $headingSettings['title']['$$type']);
        $this->assertEquals('Translated heading', $headingSettings['title']['value']['content']['value']);
    }

    public function testMixedElementorVersionsInSamePage(): void
    {
        $data = json_encode([
            [
                'id' => 'newContainer',
                'elType' => 'e-flexbox',
                'settings' => [],
                'elements' => [[
                    'id' => 'newHeading',
                    'elType' => 'widget',
                    'widgetType' => 'e-heading',
                    'settings' => [
                        'title' => ['$$type' => 'html-v3', 'value' => ['content' => ['$$type' => 'string', 'value' => 'New heading'], 'children' => []]],
                    ],
                    'elements' => [],
                    'styles' => [],
                    'interactions' => [],
                    'editor_settings' => [],
                    'version' => '0.0',
                ]],
                'isInner' => false,
                'styles' => [],
                'interactions' => [],
                'editor_settings' => [],
                'version' => '0.0',
            ],
            [
                'id' => 'oldContainer',
                'elType' => 'container',
                'settings' => [],
                'elements' => [[
                    'id' => 'blockquote1',
                    'elType' => 'widget',
                    'widgetType' => 'blockquote',
                    'settings' => [
                        'blockquote_content' => 'Old style content',
                        'author_name' => 'John Doe',
                        'tweet_button_label' => 'Tweet',
                    ],
                    'elements' => [],
                ]],
                'isInner' => false,
            ],
        ]);

        $fields = $this->getHandler($this->makeProxy($data))->getContentFields($this->mockSubmission(), false);
        $this->assertEquals('New heading', $fields['newContainer/newHeading/title']);
    }

    public function testSourceJsonExtractsAllExpectedStrings(): void
    {
        $data = file_get_contents(__DIR__ . '/fixtures/wp-1000.json');
        $this->assertNotFalse($data);

        $proxy = $this->makeProxy($data);
        $fields = $this->getHandler($proxy)->getContentFields($this->mockSubmission(), false);

        // e-heading: title
        $this->assertContains('Atomic heading', $fields);
        // e-paragraph: paragraph
        $this->assertContains('Atomic paragraph', $fields);
        // e-button: text
        $this->assertContains('Text', $fields);
        // e-form-label: text (various)
        $this->assertContains('First name', $fields);
        $this->assertContains('Last name', $fields);
        $this->assertContains('Email', $fields);
        // e-form-input: placeholder
        $this->assertContains('your@mail.com', $fields);
        // e-form-textarea: placeholder
        $this->assertContains('Your message', $fields);
        // e-paragraph inside form success/error messages
        $this->assertContains("Great! We\u{2019}ve received your information.", $fields);
        $this->assertContains("We couldn\u{2019}t process your submission. Please retry", $fields);
    }

    public function testSourceJsonExtractsImageAttachment(): void
    {
        $data = file_get_contents(__DIR__ . '/fixtures/wp-1000.json');
        $this->assertNotFalse($data);

        $proxy = $this->makeProxy($data);
        $related = $this->getHandler($proxy)->getRelatedContent('post', 1);

        $this->assertArrayHasKey(ContentTypeHelper::POST_TYPE_ATTACHMENT, $related);
        // ID 23 is the e-image attachment ID; 24, 23, 21 are gallery IDs (old format)
        $this->assertContains(23, $related[ContentTypeHelper::POST_TYPE_ATTACHMENT]);
    }
}
