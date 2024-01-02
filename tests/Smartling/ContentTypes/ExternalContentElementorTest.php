<?php

namespace Smartling\Tests\Smartling\ContentTypes;

use Smartling\ContentTypes\ContentTypeHelper;
use Smartling\ContentTypes\ContentTypePluggableInterface;
use Smartling\ContentTypes\ExternalContentElementor;
use PHPUnit\Framework\TestCase;
use Smartling\Helpers\FieldsFilterHelper;
use Smartling\Helpers\PluginHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Helpers\WordpressLinkHelper;
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
            'realistic content with background images' => [
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
            'realistic content with icon lists' => [
                file_get_contents(__DIR__ . '/wp-836.json'),
                [
                    '6ff0959b/160f1f6a/background_image/alt' => '',
                    '6ff0959b/1e8393/7a705d82/title' => 'Connect physical assets to identities. Securely.',
                    '6ff0959b/1e8393/7c7055ad/editor' => 'Who has which key, laptop, phone, or company vehicle? Keep tabs on your physical assets from the moment they’re assigned to an employee or contingent worker—until they’re transferred to the next.',
                    '6ff0959b/1e8393/7ae4ca4e/text' => 'Book a Demo',
                    '6ff0959b/background_image/alt' => '',
                    '586becdf/1b6c981/3cca688f/16bf4607/32bc6d22/title' => 'Automated and within your control.',
                    '586becdf/1b6c981/19e85cb9/6aada102/6c20c5e6/24341c5c/28a346df/title' => 'Keep compliance in check ',
                    '586becdf/1b6c981/19e85cb9/6aada102/6c20c5e6/24341c5c/66854793/text' => 'Divider',
                    '586becdf/1b6c981/19e85cb9/6aada102/6c20c5e6/24341c5c/374e276e/editor' => '<p>Deliver complete chain of custody and auditing with a comprehensive inventory of <br />all your assets, stored on a single dashboard.</p>',
                    '586becdf/1b6c981/19e85cb9/6aada102/6c20c5e6/7ac98292/5aa77baa/title' => 'Streamline provisioning ',
                    '586becdf/1b6c981/19e85cb9/6aada102/6c20c5e6/7ac98292/4e16e5d1/text' => 'Divider',
                    '586becdf/1b6c981/19e85cb9/6aada102/6c20c5e6/7ac98292/7078632e/editor' => '<p>Identify, classify, manage and monitor all employee assets with ease—from hire to retire. And once they leave, account for all equipment without skipping a beat.</p>',
                    '586becdf/1b6c981/19e85cb9/6aada102/6c20c5e6/74200406/61567684/title' => 'Simplify their process, too ',
                    '586becdf/1b6c981/19e85cb9/6aada102/6c20c5e6/74200406/751c3935/text' => 'Divider',
                    '586becdf/1b6c981/19e85cb9/6aada102/6c20c5e6/74200406/39ec7147/editor' => '<p>Does an employee need an item replaced or reported as missing? New equipment and updated inventory is just a request form away.</p>',
                    '586becdf/background_image/alt' => '',
                    '6b42e40d/1f4376a/5e49327e/4812c0a/title' => 'New device? No problem.',
                    '6b42e40d/1f4376a/5e49327e/3c709f28/editor' => '<p>Automate the registration process whenever a new device gets added to the system.</p>',
                    '6b42e40d/1f4376a/2be61870/41115868/5ba7af89/title' => '01',
                    '6b42e40d/1f4376a/2be61870/41115868/17f48b48/editor' => '<p>Asset administrator creates or  uploads a new asset/device in  Asset Management.</p>',
                    '6b42e40d/1f4376a/2be61870/755e2ad9/682a06b7/title' => '02',
                    '6b42e40d/1f4376a/2be61870/755e2ad9/6fe8127f/editor' => '<p>Asset/device is available for assignments to all users.</p>',
                    '6b42e40d/1f4376a/2be61870/7d04155b/a4b85b1/title' => '03',
                    '6b42e40d/1f4376a/2be61870/7d04155b/69d1593/editor' => '<p>New asset/device is saved in  Asset Store.</p>',
                    '6b42e40d/1f4376a/2be61870/64e946c8/478b0913/title' => '04',
                    '6b42e40d/1f4376a/2be61870/64e946c8/4e95fd07/editor' => '<p>Complete audit record  is generated.</p>',
                    'f7bac84/d011a78/text' => 'Divider',
                    'b51ad8d/e067ee8/d7bf128/1d92969/title' => 'When they leave, your assets stay.',
                    'b51ad8d/e067ee8/d7bf128/ea9730b/editor' => '<p>Once an employee is terminated, ensure your equipment goes back where it belongs. With you.</p>',
                    'b51ad8d/e067ee8/9c41eef/0d1ee47/e79628a/title' => '01',
                    'b51ad8d/e067ee8/9c41eef/0d1ee47/1c30050/editor' => '<p>User is terminated in the HR application.</p>',
                    'b51ad8d/e067ee8/9c41eef/27895b2/b48ff22/title' => '02',
                    'b51ad8d/e067ee8/9c41eef/27895b2/3056abb/editor' => '<p>Request is routed to asset owner for collection.</p>',
                    'b51ad8d/e067ee8/9c41eef/a5999a9/cd6120b/title' => '03',
                    'b51ad8d/e067ee8/9c41eef/a5999a9/7e667de/editor' => '<p>Owner receives the asset and closes the ticket.</p>',
                    'b51ad8d/e067ee8/9c41eef/58786e8/d264be9/title' => '04',
                    'b51ad8d/e067ee8/9c41eef/58786e8/d22e6b5/editor' => '<p>Asset is marked as returned and inventory is updated.</p>',
                    'b51ad8d/e067ee8/9c41eef/d6a321f/048533c/title' => '04',
                    'b51ad8d/e067ee8/9c41eef/d6a321f/2e7cb3f/editor' => '<p>Complete audit record  is generated.</p>',
                    'b51ad8d/e067ee8/ad9e519/4de142f/text' => 'See It in Action',
                    '16bb759d/8a8c626/background_image/alt' => '',
                    '16bb759d/4aa49aa6/1ec28644/title' => 'Company’s Asset Governance Platform allows you to…',
                    '16bb759d/4aa49aa6/6b2b013b/text' => 'Divider',
                    '16bb759d/4aa49aa6/680e6676/icon_list/201399d/text' => 'Identify and  classify assets',
                    '16bb759d/4aa49aa6/680e6676/icon_list/f79feb4/text' => 'Manage and monitor assets in real time',
                    '16bb759d/4aa49aa6/680e6676/icon_list/6337496/text' => 'Track assets through their entire lifecycle',
                    '16bb759d/4aa49aa6/680e6676/icon_list/dff406a/text' => 'Audit and stay up to date on assets’ chain of custody',
                    '16bb759d/4aa49aa6/680e6676/icon_list/23411c6/text' => 'Create and maintain asset reporting',
                    '16bb759d/background_image/alt' => '',
                    'c756275/61f5fbb/text' => 'Divider',
                    'c756275/background_image/alt' => '',
                    '26468648/58305cf2/31b615ee/title' => 'We keep inventory of your:',
                    '26468648/57b6662a/460e8145/97eb277/editor' => '<p style="font-weight: 600;">Devices​</p><p>Automate the registration process whenever a new device gets added to the system.</p>',
                    '26468648/57b6662a/460e8145/31aa50b4/text' => 'Divider',
                    '26468648/57b6662a/460e8145/65d12935/editor' => '<p style="font-weight: 600;">Identities​​</p><p><span class="hotkey-layer "><span class="hotkey-layer preview-overlay is-preview-sidebar-visible">Everyone from employees, to visitors, to service technicians. If they enter your building, we can track them.</span></span></p>',
                    '26468648/57b6662a/520cd148/tabs/720b2af/tab_title' => 'Devices',
                    '26468648/57b6662a/520cd148/tabs/720b2af/tab_content' => '<p>Automate the registration process whenever a new device gets added to the system.</p>',
                    '26468648/57b6662a/520cd148/tabs/794d5ec/tab_title' => 'Identities​',
                    '26468648/57b6662a/520cd148/tabs/794d5ec/tab_content' => '<p><span class="hotkey-layer "><span class="hotkey-layer preview-overlay is-preview-sidebar-visible">Everyone from employees, to visitors, to service technicians. If they enter your building, we can track them.</span></span></p>',
                    '26468648/background_image/alt' => '',
                    'bcf5c71/6f88ef3/text' => 'Divider',
                    'bcf5c71/background_image/alt' => '',
                    'bb99f51/6acdc85/3c4eb00/title' => 'You get access to 
and management of:',
                    'bb99f51/208412d/df74b88/tabs/720b2af/tab_title' => 'Real-time policy enforcement',
                    'bb99f51/208412d/df74b88/tabs/720b2af/tab_content' => '<ul><li>Chain of custody</li><li>Maintenance schedules</li><li>Assignment audits</li><li>Tracking/loss prevention</li><li>Reports and audits</li></ul>',
                    'bb99f51/208412d/df74b88/tabs/794d5ec/tab_title' => 'Device lifecycle',
                    'bb99f51/208412d/df74b88/tabs/794d5ec/tab_content' => '<ul><li>Asset inventory</li><li>Asset criticality</li><li>Service history</li><li>Service vendors</li><li>Geofencing/location</li><li>Ownerships</li><li>Asset codes/tags</li></ul><p><span class="hotkey-layer "><span class="hotkey-layer preview-overlay is-preview-sidebar-visible"> </span></span></p>',
                    'bb99f51/208412d/df74b88/tabs/10ded84/tab_title' => 'Workflow tickets',
                    'bb99f51/208412d/df74b88/tabs/10ded84/tab_content' => '<ul><li>Asset allocation requests</li><li>Policy-driven assignments</li><li>Service tickets</li><li>Report lost/stolen asset</li><li>Recertifications</li><li>Ownership transfers</li><li>Workflow approvals</li></ul><p><span class="hotkey-layer "><span class="hotkey-layer preview-overlay is-preview-sidebar-visible"> </span></span></p>',
                    'bb99f51/background_image/alt' => '',
                    '3fc5bb6f/5444f5f8/29aa692a/65ed2329/51e8145c/title' => 'The latest word around Asset Governance.',
                    '3fc5bb6f/5444f5f8/29aa692a/4e465e45/6b5f7fd7/text' => 'Resources',
                    '3fc5bb6f/5444f5f8/6fa8e431/5019b5da/560d0785/3622a2bf/478d9c56/background_image/alt' => '',
                    '3fc5bb6f/5444f5f8/6fa8e431/5019b5da/560d0785/3622a2bf/ed00f1a/354f4dd1/title' => 'Is Policy-Based Access Control and Zero Trust the Future of Physical Security?',
                    '3fc5bb6f/5444f5f8/6fa8e431/5019b5da/560d0785/3622a2bf/ed00f1a/53a54e1d/editor' => '<p>Policy-based access control (PBAC) has…</p>',
                    '3fc5bb6f/5444f5f8/6fa8e431/5019b5da/560d0785/1afd40b8/17239ed5/732d91ea/background_image/alt' => '',
                    '3fc5bb6f/5444f5f8/6fa8e431/5019b5da/560d0785/1afd40b8/17239ed5/3066c559/41e344f0/title' => 'Enterprise Guardian Solution Sheet',
                    '3fc5bb6f/5444f5f8/6fa8e431/5019b5da/560d0785/1afd40b8/17239ed5/3066c559/261e3d92/editor' => '<p>Enterprise Guardian is the industry-leading…</p>',
                    '3fc5bb6f/5444f5f8/6fa8e431/5019b5da/560d0785/1afd40b8/8e91085/18f0712e/background_image/alt' => '',
                    '3fc5bb6f/5444f5f8/6fa8e431/5019b5da/560d0785/1afd40b8/8e91085/6219178d/362c165c/title' => 'Company dismantles another security silo with new Asset Governance solution',
                    '3fc5bb6f/5444f5f8/6fa8e431/5019b5da/560d0785/1afd40b8/8e91085/6219178d/2743599e/editor' => '<p>Cyber-physical identity access management and security…</p>',
                    '3fc5bb6f/background_image/alt' => '',
                    '7e10ae1c/4c458334/7b416def/48e3729d/77f5156d/title' => 'Start securing.',
                    '7e10ae1c/4c458334/7b416def/7c9275c1/39ffdc20/editor' => '<p><span dir="ltr" role="presentation">Stronger security is just a few questions away with our online Solution Builder. </span><span dir="ltr" role="presentation">Ready</span> <span dir="ltr" role="presentation">to connect your physical assets and digital identities?</span></p>',
                    '7e10ae1c/4c458334/7b416def/7c9275c1/10efe67d/17afef64/text' => 'Build My Solution',
                    '7e10ae1c/4c458334/background_image/alt' => '',
                ],
                [
                    'attachment' => [
                        17652,
                        16038,
                        18828,
                        18831,
                        16524,
                        9609,
                        16326,
                        16323,
                        16320,
                        18837,
                        18840,
                        6111,
                        7296,
                        10785,
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
        ], $this->getExternalContentElementor()->removeUntranslatableFieldsForUpload([
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

        return new ExternalContentElementor(
            $contentTypeHelper,
            $fieldsFilterHelper,
            $pluginHelper,
            $submissionManager,
            $proxy,
            new WordpressLinkHelper($submissionManager, $proxy),
        );
    }

    public function testMergeElementorData()
    {
        $sourceAttachmentId = 597;
        $sourceBackgroundId = 16038;
        $sourceBlogId = 1;
        $sourceIcon1Id = 16326;
        $sourceIcon2Id = 16323;
        $sourceWidgetId = 19366;
        $targetAttachmentId = 17;
        $targetBackgroundId = 37;
        $targetBlogId = 2;
        $targetIcon1Id = 13;
        $targetIcon2Id = 11;
        $targetWidgetId = 23;
        $foundSubmissionAttachment = $this->createMock(SubmissionEntity::class);
        $foundSubmissionAttachment->method('getTargetId')->willReturn($targetAttachmentId);
        $foundSubmissionBackground = $this->createMock(SubmissionEntity::class);
        $foundSubmissionBackground->method('getTargetId')->willReturn($targetBackgroundId);
        $foundSubmissionIcon1 = $this->createMock(SubmissionEntity::class);
        $foundSubmissionIcon1->method('getTargetId')->willReturn($targetIcon1Id);
        $foundSubmissionIcon2 = $this->createMock(SubmissionEntity::class);
        $foundSubmissionIcon2->method('getTargetId')->willReturn($targetIcon2Id);
        $foundSubmissionIcons = [$foundSubmissionIcon1, $foundSubmissionIcon2];
        $foundSubmissionWidget = $this->createMock(SubmissionEntity::class);
        $foundSubmissionWidget->method('getTargetId')->willReturn($targetWidgetId);
        $translatedSubmission = $this->createMock(SubmissionEntity::class);
        $translatedSubmission->method('getSourceBlogId')->willReturn($sourceBlogId);
        $translatedSubmission->method('getTargetBlogId')->willReturn($targetBlogId);
        $submissionManager = $this->createMock(SubmissionManager::class);
        $matcher = $this->exactly(5);
        $submissionManager->expects($matcher)->method('findOne')->willReturnCallback(
            function ($value) use (
                $foundSubmissionAttachment,
                $foundSubmissionBackground,
                $foundSubmissionIcons,
                $foundSubmissionWidget,
                $matcher,
                $sourceAttachmentId,
                $sourceBackgroundId,
                $sourceBlogId,
                $sourceIcon1Id,
                $sourceIcon2Id,
                $sourceWidgetId,
                $targetBlogId,
            ) {
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
                            SubmissionEntity::FIELD_CONTENT_TYPE => ContentTypeHelper::POST_TYPE_ATTACHMENT,
                            SubmissionEntity::FIELD_SOURCE_BLOG_ID => $sourceBlogId,
                            SubmissionEntity::FIELD_TARGET_BLOG_ID => $targetBlogId,
                            SubmissionEntity::FIELD_SOURCE_ID => $sourceIcon1Id,
                        ], $value);

                        return $foundSubmissionIcons[0];
                    case 4:
                        $this->assertEquals([
                            SubmissionEntity::FIELD_CONTENT_TYPE => ContentTypeHelper::POST_TYPE_ATTACHMENT,
                            SubmissionEntity::FIELD_SOURCE_BLOG_ID => $sourceBlogId,
                            SubmissionEntity::FIELD_TARGET_BLOG_ID => $targetBlogId,
                            SubmissionEntity::FIELD_SOURCE_ID => $sourceIcon2Id,
                        ], $value);

                        return $foundSubmissionIcons[1];
                    case 5:
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

        $x = $this->getExternalContentElementor($this->createMock(WordpressFunctionProxyHelper::class), $submissionManager);

        $this->assertEquals(
            ['meta' => ['_elementor_data' => '[]']],
            $x->setContentFields(['meta' => ['_elementor_data' => '[]']], ['elementor' => []], $this->createMock(SubmissionEntity::class))
        );
        $original = json_encode(json_decode(sprintf(
            file_get_contents(__DIR__ . '/testMergeElementorData.json'),
            $sourceBackgroundId,
            $sourceAttachmentId,
            $sourceIcon1Id,
            $sourceIcon2Id,
            $sourceWidgetId,
        )));
        $expected = str_replace(
            ['<p>Left text<\/p>', '<p>Middle text<\/p>', 'Right heading', $sourceBackgroundId, $sourceAttachmentId, $sourceIcon1Id, $sourceIcon2Id, $sourceWidgetId],
            ['<p>Left text translated<\/p>', '<p>Middle text translated<\/p>', 'Right heading translated', $targetBackgroundId, $targetAttachmentId, $targetIcon1Id, $targetIcon2Id, $targetWidgetId],
            $original
        );

        $result = $x->setContentFields(['meta' => ['_elementor_data' => $original]], [
            'elementor' => [
                '590657a/b56da21/c799791/editor' => '<p>Left text translated</p>',
                '590657a/0f3ad3c/0088b31/editor' => '<p>Middle text translated</p>',
                '590657a/8798127/78d53a1/title' => 'Right heading translated',
            ]
        ], $translatedSubmission);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('meta', $result);
        $this->assertIsArray($result['meta']);
        $this->assertArrayHasKey('_elementor_data', $result['meta']);

        $this->assertEquals( // comparing as arrays here shows legible diff
            json_decode($expected, true, 512, JSON_THROW_ON_ERROR),
            json_decode($result['meta']['_elementor_data'], true, 512, JSON_THROW_ON_ERROR)
        );
    }
}
