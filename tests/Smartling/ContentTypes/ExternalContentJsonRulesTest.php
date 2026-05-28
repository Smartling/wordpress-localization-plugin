<?php

namespace Smartling\Tests\Smartling\ContentTypes;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Smartling\ContentTypes\ExternalContentJsonRules;
use Smartling\Extensions\Pluggable;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Replacers\ContentIdReplacer;
use Smartling\Replacers\ReplacerFactory;
use Smartling\Replacers\TranslateReplacer;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;
use Smartling\Tests\Mocks\WordpressFunctionsMockHelper;
use Smartling\Tuner\JsonFieldRule;
use Smartling\Tuner\JsonFieldRulesManager;

class ExternalContentJsonRulesTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        WordpressFunctionsMockHelper::injectFunctionsMocks();
    }

    public function testGetSupportLevelSupportedWhenAnyRuleExists(): void
    {
        $manager = $this->mockRulesManager([
            $this->rule('_elementor_data', '$.title', 'translate'),
        ]);
        $engine = $this->buildEngine($manager);

        $this->assertSame(Pluggable::SUPPORTED, $engine->getSupportLevel('page'));
        $this->assertSame(Pluggable::SUPPORTED, $engine->getSupportLevel('post'));
        $this->assertSame(Pluggable::SUPPORTED, $engine->getSupportLevel('custom_post_type'));
    }

    public function testGetSupportLevelNotSupportedWhenNoRules(): void
    {
        $manager = $this->mockRulesManager([]);
        $engine = $this->buildEngine($manager);

        $this->assertSame(Pluggable::NOT_SUPPORTED, $engine->getSupportLevel('page'));
    }

    public function testGetContentFieldsExtractsTranslateStringsOnly(): void
    {
        $json = json_encode([
            'elements' => [
                ['settings' => ['title' => 'Hello world', 'id' => 42]],
                ['settings' => ['title' => 'Second title', 'id' => 99]],
            ],
        ]);
        $manager = $this->mockRulesManager([
            $this->rule('_elementor_data', '$.elements[*].settings.title', 'translate'),
            $this->rule('_elementor_data', '$.elements[*].settings.id',    'related|attachment'),
            $this->rule('_elementor_data', '$.foo',                        'copy'),
        ]);
        $wpProxy = $this->createMock(WordpressFunctionProxyHelper::class);
        $wpProxy->method('getPostMeta')->willReturn($json);

        $engine = $this->buildEngine($manager, $wpProxy);
        $submission = $this->submission('page', 100);

        $result = $engine->getContentFields($submission, false);

        $this->assertCount(2, $result);
        $this->assertSame('Hello world', $result['_elementor_data|$.elements[*].settings.title|0']);
        $this->assertSame('Second title', $result['_elementor_data|$.elements[*].settings.title|1']);
    }

    public function testGetRelatedContentExtractsReferenceIdsGroupedByContentType(): void
    {
        $json = json_encode([
            'elements' => [
                ['settings' => ['image' => ['id' => 11]]],
                ['settings' => ['image' => ['id' => 22]]],
                ['settings' => ['image' => ['id' => 11]]],
            ],
        ]);
        $manager = $this->mockRulesManager([
            $this->rule('_elementor_data', '$.elements[*].settings.image.id', 'related|attachment'),
        ]);
        $wpProxy = $this->createMock(WordpressFunctionProxyHelper::class);
        $wpProxy->method('getPostMeta')->willReturn($json);

        $result = $this->buildEngine($manager, $wpProxy)->getRelatedContent('page', 100);

        $this->assertArrayHasKey('attachment', $result);
        $this->assertSame([11, 22], $result['attachment']);
    }

    public function testSetContentFieldsAppliesTranslationsAndIdReplacement(): void
    {
        $sourceJson = json_encode([
            'elements' => [
                ['settings' => ['title' => 'Hello', 'image' => ['id' => 11]]],
                ['settings' => ['title' => 'World', 'image' => ['id' => 22]]],
            ],
        ]);
        $manager = $this->mockRulesManager([
            $this->rule('_elementor_data', '$.elements[*].settings.title', 'translate'),
            $this->rule('_elementor_data', '$.elements[*].settings.image.id', 'related|attachment'),
        ]);
        $submission = $this->submission('page', 100);

        $submissionManager = $this->createMock(SubmissionManager::class);
        $submissionManager->method('findOne')->willReturnCallback(function (array $params) {
            $remap = [11 => 110, 22 => 220];
            $sourceId = $params[SubmissionEntity::FIELD_SOURCE_ID] ?? null;
            if (!isset($remap[$sourceId])) {
                return null;
            }
            $related = $this->createMock(SubmissionEntity::class);
            $related->method('getTargetId')->willReturn($remap[$sourceId]);
            $related->method('getId')->willReturn(0);
            return $related;
        });

        $replacerFactory = new ReplacerFactory($submissionManager);
        $wpProxy = $this->createMock(WordpressFunctionProxyHelper::class);
        $engine = new ExternalContentJsonRules($manager, $replacerFactory, $wpProxy);

        $translation = [
            ExternalContentJsonRules::PLUGIN_ID => [
                '_elementor_data|$.elements[*].settings.title|0' => 'Hola',
                '_elementor_data|$.elements[*].settings.title|1' => 'Mundo',
            ],
            'meta' => [],
        ];
        $original = ['meta' => ['_elementor_data' => $sourceJson]];

        $result = $engine->setContentFields($original, $translation, $submission);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('meta', $result);
        $this->assertArrayHasKey('_elementor_data', $result['meta']);
        $this->assertArrayNotHasKey(ExternalContentJsonRules::PLUGIN_ID, $result, 'plugin-id key should be stripped');

        $decoded = json_decode($result['meta']['_elementor_data'], true);
        $this->assertSame('Hola', $decoded['elements'][0]['settings']['title']);
        $this->assertSame('Mundo', $decoded['elements'][1]['settings']['title']);
        $this->assertSame(110, $decoded['elements'][0]['settings']['image']['id']);
        $this->assertSame(220, $decoded['elements'][1]['settings']['image']['id']);
    }

    public function testSetContentFieldsPreservesPriorHandlerTranslationsOnSameKey(): void
    {
        $sourceJson = json_encode([
            'elements' => [
                ['settings' => ['title' => 'Hello', 'subtitle' => 'World']],
            ],
        ]);
        // Simulates the JSON that an upstream handler (e.g. Elementor) wrote into
        // $translation['meta']['_elementor_data'] before JsonRules ran:
        // BOTH title AND subtitle already translated.
        $priorTranslationJson = json_encode([
            'elements' => [
                ['settings' => ['title' => 'Elementor-Hola', 'subtitle' => 'Elementor-Mundo']],
            ],
        ]);
        $manager = $this->mockRulesManager([
            // JsonRules user configured a rule only for title, not subtitle.
            $this->rule('_elementor_data', '$.elements[*].settings.title', 'translate'),
        ]);
        $submission = $this->submission('page', 100);
        $engine = $this->buildEngine($manager);

        $translation = [
            ExternalContentJsonRules::PLUGIN_ID => [
                '_elementor_data|$.elements[*].settings.title|0' => 'JsonRules-Hola',
            ],
            'meta' => [
                '_elementor_data' => $priorTranslationJson,
            ],
        ];
        $original = ['meta' => ['_elementor_data' => $sourceJson]];

        $result = $engine->setContentFields($original, $translation, $submission);

        $this->assertIsArray($result);
        $decoded = json_decode($result['meta']['_elementor_data'], true);
        $this->assertSame(
            'JsonRules-Hola',
            $decoded['elements'][0]['settings']['title'],
            'JsonRules rule should overwrite the title',
        );
        $this->assertSame(
            'Elementor-Mundo',
            $decoded['elements'][0]['settings']['subtitle'],
            "Prior handler's translation on a path WITHOUT a JsonRules rule must survive",
        );
    }

    public function testWildcardArrayIndicesMatchAllOccurrencesAcrossLevels(): void
    {
        // Simulates the Elementor _elementor_data shape: a top-level array of sections,
        // each holding an array of widgets. We use a doubly-nested wildcard path —
        // exactly what the UI now emits.
        $json = json_encode([
            ['elements' => [
                ['settings' => ['title' => 'A1']],
                ['settings' => ['title' => 'A2']],
            ]],
            ['elements' => [
                ['settings' => ['title' => 'B1']],
            ]],
        ]);
        $manager = $this->mockRulesManager([
            $this->rule('_elementor_data', '$[*].elements[*].settings.title', 'translate'),
        ]);
        $wpProxy = $this->createMock(WordpressFunctionProxyHelper::class);
        $wpProxy->method('getPostMeta')->willReturn($json);
        $engine = $this->buildEngine($manager, $wpProxy);
        $submission = $this->submission('page', 100);

        $extracted = $engine->getContentFields($submission, false);
        $this->assertCount(3, $extracted, 'wildcard at both levels should extract all 3 titles');
        $this->assertContains('A1', $extracted);
        $this->assertContains('A2', $extracted);
        $this->assertContains('B1', $extracted);

        // Feed translations back and confirm they land at every position the wildcard matched.
        $translation = [
            ExternalContentJsonRules::PLUGIN_ID => array_combine(
                array_keys($extracted),
                ['A1-T', 'A2-T', 'B1-T'],
            ),
            'meta' => [],
        ];
        $original = ['meta' => ['_elementor_data' => $json]];

        $result = $engine->setContentFields($original, $translation, $submission);

        $this->assertIsArray($result);
        $decoded = json_decode($result['meta']['_elementor_data'], true);
        $this->assertSame('A1-T', $decoded[0]['elements'][0]['settings']['title']);
        $this->assertSame('A2-T', $decoded[0]['elements'][1]['settings']['title']);
        $this->assertSame('B1-T', $decoded[1]['elements'][0]['settings']['title']);
    }

    public function testRemoveUntranslatableFieldsStripsCoveredMetaKeys(): void
    {
        $manager = $this->mockRulesManager([
            $this->rule('_elementor_data', '$.x', 'translate'),
        ]);
        $engine = $this->buildEngine($manager);

        $result = $engine->removeUntranslatableFieldsForUpload([
            'entity' => ['post_content' => 'preserved'],
            'meta'   => ['_elementor_data' => '...', '_other' => 'kept'],
        ], $this->submission('page', 100));

        $this->assertArrayNotHasKey('_elementor_data', $result['meta']);
        $this->assertArrayHasKey('_other', $result['meta']);
        $this->assertSame('preserved', $result['entity']['post_content']);
    }

    private function rule(string $metaKey, string $path, string $replacerId): JsonFieldRule
    {
        return new JsonFieldRule($metaKey, $path, $replacerId);
    }

    /**
     * @param JsonFieldRule[] $rules
     */
    private function mockRulesManager(array $rules): JsonFieldRulesManager|MockObject
    {
        $mock = $this->createMock(JsonFieldRulesManager::class);
        $mock->method('listItems')->willReturn($rules);
        return $mock;
    }

    private function buildEngine(JsonFieldRulesManager $manager, ?WordpressFunctionProxyHelper $wpProxy = null): ExternalContentJsonRules
    {
        $submissionManager = $this->createMock(SubmissionManager::class);
        return new ExternalContentJsonRules(
            $manager,
            new ReplacerFactory($submissionManager),
            $wpProxy ?? $this->createMock(WordpressFunctionProxyHelper::class),
        );
    }

    private function submission(string $contentType, int $sourceId): SubmissionEntity|MockObject
    {
        $submission = $this->createMock(SubmissionEntity::class);
        $submission->method('getContentType')->willReturn($contentType);
        $submission->method('getSourceId')->willReturn($sourceId);
        $submission->method('getSourceBlogId')->willReturn(1);
        $submission->method('getTargetBlogId')->willReturn(2);
        $submission->method('getId')->willReturn(0);
        return $submission;
    }
}
