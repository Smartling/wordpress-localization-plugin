<?php

namespace Smartling\Tests\Smartling\ContentTypes;

use Smartling\ContentTypes\ContentTypeHelper;
use Smartling\ContentTypes\ContentTypePluggableInterface;
use Smartling\ContentTypes\ExternalContentElementor;
use PHPUnit\Framework\TestCase;
use Smartling\Helpers\FieldsFilterHelper;
use Smartling\Helpers\PluginHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Services\ContentRelationsDiscoveryService;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

class ExternalContentElementorTest extends TestCase {
    public function testCanHandle()
    {
        $proxy = $this->createMock(WordpressFunctionProxyHelper::class);
        $proxy->method('getPostMeta')->willReturn('', []);
        $proxy->method('get_plugins')->willReturn(['elementor/elementor.php' => []]);
        $proxy->method('is_plugin_active')->willReturn(true);
        $this->assertEquals(ContentTypePluggableInterface::NOT_SUPPORTED, $this->getExternalContentElementor($proxy)->getSupportLevel('post', 1));
        $this->assertEquals(ContentTypePluggableInterface::SUPPORTED, $this->getExternalContentElementor($proxy)->getSupportLevel('post', 1));
    }

    /**
     * @dataProvider extractElementorDataProvider
     */
    public function testExtractElementorData(string $meta, array $expectedStrings, array $expectedRelatedContent)
    {
        $proxy = $this->createMock(WordpressFunctionProxyHelper::class);
        $proxy->method('getPostMeta')->willReturn($meta);
        $this->assertEquals($expectedStrings, $this->getExternalContentElementor($proxy)->getContentFields($this->createMock(SubmissionEntity::class), false));
        $this->assertEquals($expectedRelatedContent, $this->getExternalContentElementor($proxy)->getRelatedContent('', 0));
    }

    public function extractElementorDataProvider(): array
    {
        return [
            'empty content' => [
                '[]',
                [],
                [],
            ],
            'simple content' => [
                '[{"id":"590657a","elType":"section","settings":{"structure":"30"},"elements":[{"id":"b56da21","elType":"column","settings":{"_column_size":33,"_inline_size":null},"elements":[{"id":"c799791","elType":"widget","settings":{"editor":"<p>Left text<\/p>"},"elements":[],"widgetType":"text-editor"}],"isInner":false},{"id":"0f3ad3c","elType":"column","settings":{"_column_size":33,"_inline_size":null},"elements":[{"id":"0088b31","elType":"widget","settings":{"editor":"<p>Middle text<\/p>"},"elements":[],"widgetType":"text-editor"}],"isInner":false},{"id":"8798127","elType":"column","settings":{"_column_size":33,"_inline_size":null},"elements":[{"id":"78d53a1","elType":"widget","settings":{"title":"Right heading"},"elements":[],"widgetType":"heading"}],"isInner":false}],"isInner":false},{"id":"7a874c7","elType":"section","settings":[],"elements":[{"id":"d7d603e","elType":"column","settings":{"_column_size":100,"_inline_size":null},"elements":[{"id":"ea10188","elType":"widget","settings":{"image":{"url":"http:\/\/localhost.localdomain\/wp-content\/uploads\/2021\/09\/elementor-image.png","id":597,"alt":"","source":"library"},"image_size":"medium"},"elements":[],"widgetType":"image"}],"isInner":false}],"isInner":false}]',
                [
                    '590657a/b56da21/c799791/editor' => '<p>Left text</p>',
                    '590657a/0f3ad3c/0088b31/editor' => '<p>Middle text</p>',
                    '590657a/8798127/78d53a1/title' => 'Right heading',
                    '7a874c7/d7d603e/ea10188/image/alt' => '',
                ],
                [ContentTypeHelper::POST_TYPE_ATTACHMENT => [597]],
            ],
            'background overlay' => [
                '[{"id":"b809dba","elType":"section","settings":{"background_background":"classic","background_image":{"url":"https:\/\/test.com\/wp-content\/uploads\/2023\/08\/gradient-circle-mask.png","id":15546,"size":"","alt":"Alt text in a background","source":"library"}},"elements":[]}]',
                ['b809dba/background_image/alt' => 'Alt text in a background'],
                [ContentTypeHelper::POST_TYPE_ATTACHMENT => [15546]],
            ],
            'mixed related content' => [
                '[{"id":"ea10188","elType":"widget","settings":{"image":{"url":"http:\/\/localhost.localdomain\/wp-content\/uploads\/2021\/09\/elementor-image.png","id":597,"alt":"","source":"library"},"image_size":"medium"},"elements":[],"widgetType":"image"},{"id":"3b9b893","elType":"widget","settings":{"title":"I\'m actually a global widget"},"elements":[],"widgetType":"global","templateID":19366},{"id":"ea10189","elType":"widget","settings":{"image":{"url":"http:\/\/localhost.localdomain\/wp-content\/uploads\/2021\/09\/elementor-image*2.png","id":598,"alt":"","source":"library"},"image_size":"medium"},"elements":[],"widgetType":"image"}]',
                [
                    '3b9b893/title' => "I'm actually a global widget",
                    'ea10188/image/alt' => '',
                    'ea10189/image/alt' => '',
                ],
                [
                    ContentRelationsDiscoveryService::POST_BASED_PROCESSOR => [19366],
                    ContentTypeHelper::POST_TYPE_ATTACHMENT => [597, 598],
                ]
            ],
            'global widget ' => [
                '[{"id":"3b9b893","elType":"widget","settings":{"title":"I\'m actually a global widget"},"elements":[],"widgetType":"global","templateID":19366}]',
                [
                    '3b9b893/title' => "I'm actually a global widget",
                ],
                [
                    ContentRelationsDiscoveryService::POST_BASED_PROCESSOR => [19366],
                ],
            ],
            'realistic content with empty background image ids' => [
                file_get_contents(__DIR__ . '/wp-834.json'),
                [
                    '10733aaf/215ff951/background_image/alt' => '',
                    '10733aaf/43212dc7/14c1dc16/title' => 'Now in Company Wallet and Company: Breezy and secure workforce access.',
                    '10733aaf/43212dc7/7d83b076/editor' => '<p>Replace those plastic physical access cards with a smarter and convenient alternative right inside Company and Company Wallet. Then seamlessly manage your workforce as they securely breeze through company doors with just a quick tap of their phone or other smart device. Added perk: Your ESG stakeholders approve.</p>',
                    '10733aaf/43212dc7/255664fd/text' => 'Book a Demo',
                    '10733aaf/background_image/alt' => '',
                    '2a433ce4/7d1ab612/4cb836df/title' => 'Benefits that start
where security
threats end.',
                    '2a433ce4/7aa00848/5bb30671/editor' => '<p>Stronger security is everyone’s priority. But when you go all-in on NFC Wallet mobile credentials, there’s even more to look forward to.</p><ul><li><strong>Inside. In seconds.</strong><br />Employees can walk in simply by holding their Device or Device to an <a href="https://www.company.com/product-mix/readers" target="_blank" rel="noopener">Company</a> or <a href="https://www.company.com/readers/product" target="_blank" rel="noopener">Company</a> reader—no need to unlock or even wake up their device.</li><li><strong>Battery running low?</strong><br />No sweat. Offices and amenity areas can be accessed for up to five hours with Power Reserve.</li><li><strong>Tap into more and better experiences.</strong><br />Building access is only the beginning. Empower employees to also unlock office doors, print documents and access vending machines with ease.</li><li><strong>Reduce your carbon footprint.</strong><br />Ramp up your Environmental, Social and Governance (ESG) initiatives with smarter, mobile-friendly workspace access.</li></ul>',
                    '2a433ce4/7aa00848/36d6b938/text' => 'See It in Action',
                    '2a433ce4/background_image/alt' => '',
                    '553a03c9/2dc52381/2d1e0c5d/title' => 'Streamline things for your security team, too.',
                    '553a03c9/29e0a994/526b6bc1/editor' => '<p data-pm-slice="1 1 []">Guardian manages the entire NFC mobile wallet credential lifecycle, so your security team can ensure the most secure front-end experience alongside back-end control. Think better automation, smarter data and all-around stronger governance.</p>',
                    '553a03c9/background_image/alt' => '',
                    '67e46bf1/34e7ec01/background_image/alt' => '',
                    '67e46bf1/6fe3c327/56bcbe4b/title' => 'I also want to ...',
                    '67e46bf1/6fe3c327/1c9bd6b9/editor' => '<p>Manage insider threats and optimize physical workspaces.</p>',
                    '67e46bf1/6fe3c327/39b92be/52df86ad/text' => 'Securely Open Digital Doors',
                    '67e46bf1/6fe3c327/39b92be/600c314/text' => ' Manage Who Has Access and When',
                    '67e46bf1/background_image/alt' => '',
                    '241f2a40/376cd8bf/186d353f/1317333c/51037771/title' => 'Open doors to more insights.',
                    '241f2a40/376cd8bf/186d353f/475245c8/1632b8fd/text' => 'Resources',
                    '241f2a40/376cd8bf/a3f1d77/2e1ae9a8/a9dc109/56d3b64/15ab011/background_image/alt' => '',
                    '241f2a40/376cd8bf/a3f1d77/2e1ae9a8/a9dc109/56d3b64/62b0244/9aba319/title' => 'NFC Wallet Mobile Credentials Data Sheet',
                    '241f2a40/376cd8bf/a3f1d77/2e1ae9a8/a9dc109/56d3b64/62b0244/c9ac118/editor' => '<p>Mobile credentials are rapidly gaining popularity across...</p>',
                    '241f2a40/376cd8bf/a3f1d77/2e1ae9a8/a9dc109/52e7109/3e78aa4/418ab13/background_image/alt' => '',
                    '241f2a40/376cd8bf/a3f1d77/2e1ae9a8/a9dc109/52e7109/3e78aa4/a970a87/c602aa6/title' => 'Company Launches NFC Wallet Mobile Credentials Powered by Company',
                    '241f2a40/376cd8bf/a3f1d77/2e1ae9a8/a9dc109/52e7109/3e78aa4/a970a87/74ec592/editor' => '<p>Location, ST – Company, Inc., the leading physical identity access</p>',
                    '241f2a40/376cd8bf/a3f1d77/2e1ae9a8/a9dc109/52e7109/f5b359a/dbd3164/background_image/alt' => 'Scanning company phone as entry key',
                    '241f2a40/376cd8bf/a3f1d77/2e1ae9a8/a9dc109/52e7109/f5b359a/93359d6/6a442fb/title' => 'Company Partners with Company to Offer Employee Badge in Company Wallet',
                    '241f2a40/376cd8bf/a3f1d77/2e1ae9a8/a9dc109/52e7109/f5b359a/93359d6/ba2f359/editor' => '<p>Company becomes one of the first organizations to...</p>',
                    '241f2a40/background_image/alt' => '',
                    '4770773e/53277b93/55cb6f1e/6197747a/65534e08/title' => 'No rip and replace?<br>
Yes, please.',
                    '4770773e/53277b93/55cb6f1e/70d34fd1/16791f1d/editor' => '<p><span dir="ltr" role="presentation">Our mobile credentialing solution connects with the leading PACS, HR and IT </span><span dir="ltr" role="presentation">systems, so you can start from right where you’re at.</span></p>',
                    '4770773e/53277b93/55cb6f1e/70d34fd1/3f749eeb/65cae04f/text' => 'Build My Solution',
                    '4770773e/53277b93/background_image/alt' => '',
                ],
                [
                    'attachment' => [
                        17676,
                        16038,
                        17679,
                        19584,
                        12030,
                        13661,
                        15813,
                    ],
                ],
            ],
        ];
    }

    public function testAlterContentFieldsForUpload()
    {
        $this->assertEquals([
            'entity' => [],
            'meta' => [
                'x' => 'relevant',
            ],
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

    private function getExternalContentElementor(?WordpressFunctionProxyHelper $proxy = null, ?SubmissionManager $submissionManager = null): ExternalContentElementor
    {
        $contentTypeHelper = $this->createMock(ContentTypeHelper::class);
        $contentTypeHelper->method('isPost')->willReturn(true);
        $pluginHelper = $this->createMock(PluginHelper::class);
        $pluginHelper->method('versionInRange')->willReturn(true);
        if ($proxy === null) {
            $proxy = new WordpressFunctionProxyHelper();
        }
        if ($submissionManager === null) {
            $submissionManager = $this->createMock(SubmissionManager::class);
        }
        $fieldsFilterHelper = $this->getMockBuilder(FieldsFilterHelper::class)->disableOriginalConstructor()->onlyMethods([])->getMock();

        return new ExternalContentElementor($contentTypeHelper, $fieldsFilterHelper, $pluginHelper, $submissionManager, $proxy);
    }

    public function testMergeElementorData()
    {
        $sourceAttachmentId = 597;
        $sourceBackgroundId = 16038;
        $sourceBlogId = 1;
        $sourceWidgetId = 19366;
        $targetAttachmentId = 17;
        $targetBackgroundId = 37;
        $targetBlogId = 2;
        $targetWidgetId = 23;
        $foundSubmissionAttachment = $this->createMock(SubmissionEntity::class);
        $foundSubmissionAttachment->method('getTargetId')->willReturn($targetAttachmentId);
        $foundSubmissionBackground = $this->createMock(SubmissionEntity::class);
        $foundSubmissionBackground->method('getTargetId')->willReturn($targetBackgroundId);
        $foundSubmissionWidget = $this->createMock(SubmissionEntity::class);
        $foundSubmissionWidget->method('getTargetId')->willReturn($targetWidgetId);
        $translatedSubmission = $this->createMock(SubmissionEntity::class);
        $translatedSubmission->method('getSourceBlogId')->willReturn($sourceBlogId);
        $translatedSubmission->method('getTargetBlogId')->willReturn($targetBlogId);
        $submissionManager = $this->createMock(SubmissionManager::class);
        $matcher = $this->exactly(3);
        $submissionManager->expects($matcher)->method('findOne')->willReturnCallback(
            function ($value) use ($foundSubmissionAttachment, $foundSubmissionBackground, $foundSubmissionWidget, $matcher, $sourceAttachmentId, $sourceBackgroundId, $sourceBlogId, $sourceWidgetId, $targetBlogId) {
                switch ($matcher->getInvocationCount()) {
                    case 1:
                        $this->assertEquals([
                            SubmissionEntity::FIELD_CONTENT_TYPE => ContentTypeHelper::POST_TYPE_ATTACHMENT,
                            SubmissionEntity::FIELD_SOURCE_BLOG_ID => $sourceBlogId,
                            SubmissionEntity::FIELD_TARGET_BLOG_ID => $targetBlogId,
                            SubmissionEntity::FIELD_SOURCE_ID => $sourceAttachmentId,
                        ], $value);

                        return $foundSubmissionAttachment;
                    case 2:
                        $this->assertEquals([
                            SubmissionEntity::FIELD_CONTENT_TYPE => ContentTypeHelper::POST_TYPE_ATTACHMENT,
                            SubmissionEntity::FIELD_SOURCE_BLOG_ID => $sourceBlogId,
                            SubmissionEntity::FIELD_TARGET_BLOG_ID => $targetBlogId,
                            SubmissionEntity::FIELD_SOURCE_ID => $sourceBackgroundId,
                        ], $value);

                        return $foundSubmissionBackground;
                    case 3:
                        $this->assertEquals([
                            SubmissionEntity::FIELD_CONTENT_TYPE => ExternalContentElementor::CONTENT_TYPE_ELEMENTOR_LIBRARY,
                            SubmissionEntity::FIELD_SOURCE_BLOG_ID => $sourceBlogId,
                            SubmissionEntity::FIELD_TARGET_BLOG_ID => $targetBlogId,
                            SubmissionEntity::FIELD_SOURCE_ID => $sourceWidgetId,
                        ], $value);

                        return $foundSubmissionWidget;
                }
                throw new \LogicException('Unexpected invocation');
            }
        );

        $x = $this->getExternalContentElementor(null, $submissionManager);

        $this->assertEquals(
            ['meta' => ['_elementor_data' => '[]']],
            $x->setContentFields(['meta' => ['_elementor_data' => '[]']], ['elementor' => []], $this->createMock(SubmissionEntity::class))
        );
        $original = json_encode(json_decode(sprintf(file_get_contents(__DIR__ . '/testMergeElementorData.json'), $sourceBackgroundId, $sourceAttachmentId, $sourceWidgetId)));
        $expected = str_replace(
            ['<p>Left text<\/p>', '<p>Middle text<\/p>', 'Right heading', $sourceBackgroundId, $sourceAttachmentId, $sourceWidgetId],
            ['<p>Left text translated<\/p>', '<p>Middle text translated<\/p>', 'Right heading translated', $targetBackgroundId, $targetAttachmentId, $targetWidgetId],
            $original
        );

        $this->assertEquals(
            ['meta' => ['_elementor_data' => $expected]],
            $x->setContentFields(['meta' => ['_elementor_data' => $original]], ['elementor' => [
            '590657a/b56da21/c799791/editor' => '<p>Left text translated</p>',
            '590657a/0f3ad3c/0088b31/editor' => '<p>Middle text translated</p>',
            '590657a/8798127/78d53a1/title' => 'Right heading translated',
        ]], $translatedSubmission));
    }
}
