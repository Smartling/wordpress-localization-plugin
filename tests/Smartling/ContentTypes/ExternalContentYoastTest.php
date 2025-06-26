<?php

namespace Smartling\ContentTypes;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Smartling\Extensions\Acf\AcfDynamicSupport;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\ContentSerializationHelper;
use Smartling\Helpers\FieldsFilterHelper;
use Smartling\Helpers\PlaceholderHelper;
use Smartling\Helpers\PluginHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

class ExternalContentYoastTest extends TestCase {
    private int $sourcePostId = 1;
    private array $originalMetaForProcessing = [
        'meta' => [
            '_yoast_wpseo_focuskeywords' => '[{"keyword":"Ryan Test","score":33},{"keyword":"TEST 2 JARON","score":33},{"keyword":"FRIDAY","score":47}]',
            '_yoast_wpseo_keywordsynonyms' => '["","","",""]',
            '_yoast_wpseo_title' => '%%title%% %%page%% %%sep%% %%sitename%%',
            'preservedfield' => 'value',
        ],
    ];
    private array $originalMeta = [
        'meta' => [
            '_yoast_wpseo_focuskeywords' => 'Some plain keyword string',
            '_yoast_wpseo_title' => '%%title%% %%page%% %%sep%% %%sitename%%',
            'preservedfield' => 'value',
        ],
    ];
    private array $expectedProcessedMeta = [
        '_yoast_wpseo_focuskeywords/0/keyword' => 'Ryan Test',
        '_yoast_wpseo_focuskeywords/0/score' => '33',
        '_yoast_wpseo_focuskeywords/1/keyword' => 'TEST 2 JARON',
        '_yoast_wpseo_focuskeywords/1/score' => '33',
        '_yoast_wpseo_focuskeywords/2/keyword' => 'FRIDAY',
        '_yoast_wpseo_focuskeywords/2/score' => '47',
        '_yoast_wpseo_keywordsynonyms/0' => '',
        '_yoast_wpseo_keywordsynonyms/1' => '',
        '_yoast_wpseo_keywordsynonyms/2' => '',
        '_yoast_wpseo_keywordsynonyms/3' => '',
        '_yoast_wpseo_title' => PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_START . '%%title%%' . PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_END . ' ' .
            PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_START . '%%page%%' . PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_END . ' ' .
            PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_START . '%%sep%%' . PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_END . ' ' .
            PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_START . '%%sitename%%' . PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_END,
    ];

    public function testGetContentFieldsProcessed()
    {
        $this->assertEquals($this->expectedProcessedMeta, $this->getExternalContentYoast($this->getWpProxyForProcessedStrings())
            ->getContentFields($this->getSubmission(), true));
    }

    public function testGetContentFields()
    {
        $this->assertEquals([
            '_yoast_wpseo_title' => PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_START . '%%title%%' . PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_END . ' ' .
                PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_START . '%%page%%' . PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_END . ' ' .
                PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_START . '%%sep%%' . PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_END . ' ' .
                PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_START . '%%sitename%%' . PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_END
        ], $this->getExternalContentYoast($this->getWpProxy())
            ->getContentFields($this->getSubmission(), true));
    }

    public function testRemoveUntranslatableFieldsForUploadProcessed()
    {
        $this->assertEquals(
            ['meta' => ['preservedfield' => 'value']],
            $this->getExternalContentYoast($this->getWpProxyForProcessedStrings())
                ->removeUntranslatableFieldsForUpload($this->originalMetaForProcessing, $this->getSubmission()),
        );
    }

    public function testRemoveUntranslatableFieldsForUpload()
    {
        $expected = $this->originalMeta;
        unset($expected['meta']['_yoast_wpseo_title']);
        $this->assertEquals(
            $expected,
            $this->getExternalContentYoast($this->getWpProxy())
                ->removeUntranslatableFieldsForUpload($this->originalMeta, $this->getSubmission()),
        );
    }

    public function testSetContentFieldsProcessed()
    {
        $this->assertEquals([
            'meta' => [
                '_yoast_wpseo_focuskeywords' => '[{"keyword":"Ryan Test Translated","score":33},{"keyword":"TEST 2 JARON Translated","score":33},{"keyword":"FRIDAY Translated","score":47}]',
            ],
        ], $this->getExternalContentYoast($this->createMock(WordpressFunctionProxyHelper::class))
            ->setContentFields(array_merge($this->originalMetaForProcessing, (new ArrayHelper())->structurize(['yoast' => $this->expectedProcessedMeta])), [
                'yoast' => [
                    '_yoast_wpseo_focuskeywords' => [
                        ['keyword' => 'Ryan Test Translated'],
                        ['keyword' => 'TEST 2 JARON Translated'],
                        ['keyword' => 'FRIDAY Translated'],
                    ],
                ],
            ], $this->getSubmission()));
    }

    private function getExternalContentYoast(WordpressFunctionProxyHelper $wpProxy): ExternalContentYoast
    {
        return new ExternalContentYoast(
            $this->createMock(ContentTypeHelper::class),
            $this->getFieldsFilterHelper(),
            new PlaceholderHelper(),
            $this->createMock(PluginHelper::class),
            $this->createMock(SubmissionManager::class),
            $wpProxy,
        );
    }

    private function getWpProxyForProcessedStrings(): WordpressFunctionProxyHelper|MockObject
    {
        $wpProxy = $this->createMock(WordpressFunctionProxyHelper::class);
        $wpProxy->method('getPostMeta')
            ->willReturnCallback(function (int $id, string $field) {
                $this->assertEquals($this->sourcePostId, $id);
                $this->assertContains($field, array_merge(ExternalContentYoast::decodedFields, ExternalContentYoast::placeholderFields));

                return $this->originalMetaForProcessing['meta'][$field] ?? ''; // WordPress returns empty string on unknown key
            });

        return $wpProxy;
    }

    private function getWpProxy(): WordpressFunctionProxyHelper|MockObject
    {
        $wpProxy = $this->createMock(WordpressFunctionProxyHelper::class);
        $wpProxy->method('getPostMeta')
            ->willReturnCallback(function (int $id, string $field) {
                $this->assertEquals($this->sourcePostId, $id);
                $this->assertContains($field, array_merge(ExternalContentYoast::decodedFields, ExternalContentYoast::placeholderFields));

                return $this->originalMeta['meta'][$field] ?? '';
            });

        return $wpProxy;
    }

    private function getSubmission(): SubmissionEntity|MockObject
    {
        $submission = $this->createMock(SubmissionEntity::class);
        $submission->method('getSourceId')->willReturn($this->sourcePostId);

        return $submission;
    }

    private function getFieldsFilterHelper(): FieldsFilterHelper
    {
        return new FieldsFilterHelper(
            $this->createMock(AcfDynamicSupport::class),
            $this->createMock(ContentSerializationHelper::class),
            $this->createMock(SettingsManager::class),
            $this->createMock(WordpressFunctionProxyHelper::class),
        );
    }
}
