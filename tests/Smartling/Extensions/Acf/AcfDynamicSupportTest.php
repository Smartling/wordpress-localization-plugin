<?php

namespace Smartling\Extensions\Acf;

use PHPUnit\Framework\TestCase;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\SiteHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Replacers\ReplacerFactory;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

class AcfDynamicSupportTest extends TestCase
{
    protected function setUp(): void
    {
        defined('ARRAY_A') || define('ARRAY_A', 'ARRAY_A');
        parent::setUp();
    }

    public function testGetReplacerIdForField()
    {
        $x = new class(
            new ArrayHelper(),
            $this->createMock(SettingsManager::class),
            $this->createMock(SiteHelper::class),
            $this->createMock(SubmissionManager::class),
            $this->createMock(WordpressFunctionProxyHelper::class),
        ) extends AcfDynamicSupport {
            public function run(): void
            {
            }
        };
        $x->addCopyRules(['field_bbbbbbbbbbbbb']);
        $this->assertEquals(ReplacerFactory::REPLACER_COPY, $x->getReplacerIdForField(
            ['someAttribute' => 'test', '_someAttribute' => 'field_aaaaaaaaaaaaa_field_bbbbbbbbbbbbb'],
            'someAttribute',
        ));
    }

    public function testSyncFieldGroup()
    {
        $sourceBlogId = 1;
        $targetBlogId = 7;
        $fieldGroupSubmissionTargetId = 11;

        $fieldGroupSubmission = $this->createMock(SubmissionEntity::class);
        $fieldGroupSubmission->method('getContentType')->willReturn(AcfDynamicSupport::POST_TYPE_GROUP);
        $fieldGroupSubmission->method('getSourceBlogId')->willReturn($sourceBlogId);
        $fieldGroupSubmission->method('getSourceId')->willReturn(3);
        $fieldGroupSubmission->method('getTargetBlogId')->willReturn($targetBlogId);
        $fieldGroupSubmission->method('getTargetId')->willReturn($fieldGroupSubmissionTargetId);

        $pageSourceId = 4052;
        $pageTargetId = 12;

        $submission4052 = $this->createMock(SubmissionEntity::class);
        $submission4052->method('getContentType')->willReturn('post');
        $submission4052->method('getSourceBlogId')->willReturn($sourceBlogId);
        $submission4052->method('getSourceId')->willReturn($pageSourceId);
        $submission4052->method('getTargetBlogId')->willReturn($targetBlogId);
        $submission4052->method('getTargetId')->willReturn($pageTargetId);

        $postSubmissions = [
            $pageSourceId => $submission4052,
        ];

        $submissionManager = $this->createMock(SubmissionManager::class);
        $submissionManager->method('findOne')->willReturnCallback(function ($arguments) use ($postSubmissions) {
            return $postSubmissions[$arguments[SubmissionEntity::FIELD_SOURCE_ID]] ?? null;
        });

        $wpProxy = $this->createMock(WordpressFunctionProxyHelper::class);
        $wpProxy->method('maybe_unserialize')->willReturnCallback(function ($data) {
            return unserialize($data);
        });
        $source = [
            'location' => [
                [['param' => 'post_type', 'operator' => '==', 'value' => 'video']],
                [['param' => 'post_type', 'operator' => '==', 'value' => 'blog']],
                [['param' => 'post_type', 'operator' => '==', 'value' => 'bt_event']],
                [['param' => 'post_type', 'operator' => '==', 'value' => 'bt_news']],
                [['param' => 'post_type', 'operator' => '==', 'value' => 'report']],
                [['param' => 'post_type', 'operator' => '==', 'value' => 'webinar']],
                [['param' => 'post_type', 'operator' => '==', 'value' => 'solution-guide']],
                [['param' => 'post_type', 'operator' => '==', 'value' => 'ebook']],
                [['param' => 'post_type', 'operator' => '==', 'value' => 'whitepaper']],
                [['param' => 'page', 'operator' => '==', 'value' => (string)$pageSourceId]],
                [['param' => 'page', 'operator' => '==', 'value' => '690']],
                [['param' => 'page', 'operator' => '==', 'value' => '2111']],
                [['param' => 'page', 'operator' => '==', 'value' => '1662']],
                [['param' => 'page', 'operator' => '==', 'value' => '29']],
                [['param' => 'page', 'operator' => '==', 'value' => '31']],
                [['param' => 'page', 'operator' => '==', 'value' => '32']],
                [['param' => 'page', 'operator' => '==', 'value' => '33']],
                [['param' => 'page', 'operator' => '==', 'value' => '30']],
                [['param' => 'page', 'operator' => '==', 'value' => '818']],
                [['param' => 'page', 'operator' => '==', 'value' => '98']],
                [['param' => 'page', 'operator' => '==', 'value' => '824']],
                [['param' => 'page', 'operator' => '==', 'value' => '3387']],
                [['param' => 'page', 'operator' => '==', 'value' => '798']],
            ],
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
            'hide_on_screen' => '',
            'description' => '',
            'show_in_rest' => 0,
        ];

        $expectedContent = $source;
        $expectedContent['location'][9][0]['value'] = (string)$pageTargetId;

        $wpProxy->method('get_post')->willReturn([
            'post_content' => serialize($source),
        ]);
        $wpProxy->expects($this->once())->method('wp_update_post')->with([
            'ID' => $fieldGroupSubmissionTargetId,
            'post_content' => serialize($expectedContent),
        ]);

        $siteHelper = $this->createMock(SiteHelper::class);
        $siteHelper->method('withBlog')->willReturnCallback(function ($blogId, $callable) {
            return $callable();
        });

        $x = new AcfDynamicSupport(
            new ArrayHelper(),
            $this->createMock(SettingsManager::class),
            $siteHelper,
            $submissionManager,
            $wpProxy
        );

        $x->syncAcfData($fieldGroupSubmission);
    }

    public function testGetRuleId()
    {
        $x = new AcfDynamicSupport(
            $this->createMock(ArrayHelper::class),
            $this->createMock(SettingsManager::class),
            $this->createMock(SiteHelper::class),
            $this->createMock(SubmissionManager::class),
            $this->createMock(WordpressFunctionProxyHelper::class),
        );
        $this->assertEquals('field_66d0680a343ff', $x->getRuleId('field_66d0680a343ff'), 'Should return rule id');
        $this->assertEquals('field_66d08bd321aee', $x->getRuleId('field_66d0680a343ff_field_66d08bd321aee'), 'Should return last part of complex rule id');
    }
}
