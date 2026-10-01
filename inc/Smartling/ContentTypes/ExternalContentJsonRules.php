<?php

namespace Smartling\ContentTypes;

use Smartling\Extensions\Pluggable;
use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Replacers\ReplacerFactory;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Tuner\JsonFieldRule;
use Smartling\Tuner\JsonFieldRulesManager;
use Smartling\Tuner\JsonLeafMatcher;
use Smartling\Vendor\JsonPath\JsonObject;

class ExternalContentJsonRules implements ContentTypeModifyingInterface
{
    use LoggerSafeTrait;

    public const PLUGIN_ID = 'json-rules';

    private JsonLeafMatcher $matcher;

    public function __construct(
        private JsonFieldRulesManager $rulesManager,
        private ReplacerFactory $replacerFactory,
        private WordpressFunctionProxyHelper $wpProxy,
        ?JsonLeafMatcher $matcher = null,
    ) {
        $this->matcher = $matcher ?? new JsonLeafMatcher();
    }

    public function getMaxVersion(): string
    {
        return '99';
    }

    public function getMinVersion(): string
    {
        return '0';
    }

    public function getPluginId(): string
    {
        return self::PLUGIN_ID;
    }

    public function getLogName(): string
    {
        return self::PLUGIN_ID;
    }

    public function getPluginPaths(): array
    {
        return [];
    }

    public function getPluginSupportLevel(): string
    {
        return Pluggable::SUPPORTED;
    }

    public function getSupportLevel(string $contentType, ?int $contentId = null): string
    {
        $this->rulesManager->loadData();
        return $this->rulesManager->listItems() === []
            ? Pluggable::NOT_SUPPORTED
            : Pluggable::SUPPORTED;
    }

    public function getExternalContentTypes(): array
    {
        return [];
    }

    public function getContentFields(SubmissionEntity $submission, bool $raw): array
    {
        $result = [];
        $this->rulesManager->loadData();
        foreach ($this->getRulesByMetaKey() as $metaKey => $rules) {
            $json = $this->readMetaJson($submission->getSourceId(), $metaKey);
            if ($json === null) {
                continue;
            }
            foreach ($rules as $rule) {
                if ($this->parseReplacer($rule->getReplacerId())[0] !== ReplacerFactory::REPLACER_TRANSLATE) {
                    continue;
                }
                if ($rule->isExtended()) {
                    foreach ($this->matcher->match($json, $rule) as $leaf) {
                        if (is_string($leaf['value']) && $leaf['value'] !== '') {
                            $result[$this->buildLeafKey($metaKey, $leaf['segments'])] = $leaf['value'];
                        }
                    }
                    continue;
                }
                $matches = $this->safeGet($json, $rule->getPropertyPath());
                foreach ($matches as $index => $value) {
                    if (is_string($value) && $value !== '') {
                        $result[$this->buildKey($metaKey, $rule->getPropertyPath(), $index)] = $value;
                    }
                }
            }
        }
        return $result;
    }

    /**
     * @return array<int, mixed> values matched by the rule in document order, used for previews
     */
    public function getMatchedValues(array $json, JsonFieldRule $rule): array
    {
        return $rule->isExtended()
            ? array_column($this->matcher->match($json, $rule), 'value')
            : array_values($this->safeGet($json, $rule->getPropertyPath()));
    }

    public function getRelatedContent(string $contentType, int $contentId): array
    {
        $result = [];
        $this->rulesManager->loadData();
        foreach ($this->getRulesByMetaKey() as $metaKey => $rules) {
            $json = $this->readMetaJson($contentId, $metaKey);
            if ($json === null) {
                continue;
            }
            foreach ($rules as $rule) {
                [$replacer, $hint] = $this->parseReplacer($rule->getReplacerId());
                if ($replacer !== ReplacerFactory::REPLACER_RELATED) {
                    continue;
                }
                $referencedType = $hint !== '' ? $hint : ContentTypeHelper::CONTENT_TYPE_UNKNOWN;
                $values = $rule->isExtended()
                    ? array_column($this->matcher->match($json, $rule), 'value')
                    : $this->safeGet($json, $rule->getPropertyPath());
                foreach ($values as $value) {
                    if (is_numeric($value) && (int)$value > 0) {
                        $result[$referencedType][] = (int)$value;
                    }
                }
            }
        }
        foreach ($result as $type => $ids) {
            $result[$type] = array_values(array_unique($ids));
        }
        return $result;
    }

    public function setContentFields(array $original, array $translation, SubmissionEntity $submission): ?array
    {
        $translations = $translation[$this->getPluginId()] ?? [];
        unset($translation[$this->getPluginId()]);

        $this->rulesManager->loadData();
        $changed = false;
        foreach ($this->getRulesByMetaKey() as $metaKey => $rules) {
            // Prefer a translation already produced by a prior handler over the source.
            // JsonRules' edits act as a delta on top of bundled handlers rather than replacing their work.
            $sourceJson = $translation['meta'][$metaKey] ?? $original['meta'][$metaKey] ?? null;
            if (!is_string($sourceJson) || $sourceJson === '') {
                continue;
            }
            try {
                $jsonObject = new JsonObject($sourceJson);
            } catch (\Throwable $e) {
                $this->getLogger()->debug("Failed to parse meta $metaKey as JSON: " . $e->getMessage());
                continue;
            }
            // Extended rules are matched against the source, not the (possibly already translated) copy being edited:
            // conditions on translatable siblings must see the same values as they did on upload
            $matchSource = $this->decodeJson($original['meta'][$metaKey] ?? null) ?? $jsonObject->getValue();
            $modified = false;
            foreach ($rules as $rule) {
                [$replacer] = $this->parseReplacer($rule->getReplacerId());
                if ($replacer === ReplacerFactory::REPLACER_TRANSLATE) {
                    $modified = $this->applyTranslateRule($jsonObject, $matchSource, $rule, $metaKey, $translations) || $modified;
                } elseif ($replacer === ReplacerFactory::REPLACER_RELATED) {
                    $modified = $this->applyRelatedRule($jsonObject, $matchSource, $rule, $submission) || $modified;
                }
            }
            if ($modified) {
                $translation['meta'][$metaKey] = $jsonObject->getJson(JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $changed = true;
            }
        }

        return $changed ? $translation : null;
    }

    public function removeUntranslatableFieldsForUpload(array $source, SubmissionEntity $submission): array
    {
        $this->rulesManager->loadData();
        foreach (array_keys($this->getRulesByMetaKey()) as $metaKey) {
            if (isset($source['meta'][$metaKey])) {
                unset($source['meta'][$metaKey]);
            }
        }
        return $source;
    }

    /**
     * @return array<string, JsonFieldRule[]>
     */
    private function getRulesByMetaKey(): array
    {
        $result = [];
        foreach ($this->rulesManager->listItems() as $rule) {
            $result[$rule->getMetaKey()][] = $rule;
        }
        return $result;
    }

    private function readMetaJson(int $contentId, string $metaKey): ?array
    {
        $value = $this->wpProxy->getPostMeta($contentId, $metaKey, true);
        if (!is_string($value) || $value === '') {
            return null;
        }
        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        return is_array($decoded) ? $decoded : null;
    }

    private function safeGet(array $json, string $path): array
    {
        try {
            $result = (new JsonObject($json))->get($path);
        } catch (\Throwable $e) {
            $this->getLogger()->debug("JsonPath get failed for path=$path: " . $e->getMessage());
            return [];
        }
        if (!is_array($result)) {
            return [];
        }
        return $result;
    }

    private function decodeJson(mixed $value): ?array
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    private function applyTranslateRule(JsonObject $jsonObject, array $matchSource, JsonFieldRule $rule, string $metaKey, array $translations): bool
    {
        if ($rule->isExtended()) {
            $data = &$jsonObject->getValue();
            $changed = false;
            foreach ($this->matcher->match($matchSource, $rule) as $leaf) {
                $key = $this->buildLeafKey($metaKey, $leaf['segments']);
                if (array_key_exists($key, $translations)) {
                    $changed = $this->matcher->setValue($data, $leaf['segments'], $translations[$key]) || $changed;
                }
            }
            unset($data);

            return $changed;
        }
        $objects = $jsonObject->getJsonObjects($rule->getPropertyPath());
        if ($objects === false || $objects === null) {
            return false;
        }
        if (!is_array($objects)) {
            $objects = [$objects];
        }
        $changed = false;
        foreach ($objects as $index => $node) {
            $key = $this->buildKey($metaKey, $rule->getPropertyPath(), $index);
            if (array_key_exists($key, $translations)) {
                $ref = &$node->getValue();
                $ref = $translations[$key];
                unset($ref);
                $changed = true;
            }
        }
        return $changed;
    }

    private function applyRelatedRule(JsonObject $jsonObject, array $matchSource, JsonFieldRule $rule, SubmissionEntity $submission): bool
    {
        try {
            $replacer = $this->replacerFactory->getReplacer($rule->getReplacerId());
        } catch (\Throwable $e) {
            $this->getLogger()->notice("Unable to resolve replacer {$rule->getReplacerId()}: " . $e->getMessage());
            return false;
        }
        if ($rule->isExtended()) {
            $data = &$jsonObject->getValue();
            $changed = false;
            foreach ($this->matcher->match($matchSource, $rule) as $leaf) {
                // Replace from the source id: ids another handler already remapped in the copy must not be remapped
                // again, a target id can collide with an unrelated source id in multisite
                $original = $leaf['value'];
                if (!is_numeric($original) || (int)$original <= 0) {
                    continue;
                }
                $replaced = $replacer->processAttributeOnDownload($original, $original, $submission);
                if ($replaced !== $original) {
                    $changed = $this->matcher->setValue($data, $leaf['segments'], $replaced) || $changed;
                }
            }
            unset($data);

            return $changed;
        }
        $objects = $this->getNodes($jsonObject, $rule->getPropertyPath());
        // Read ids from the source, the copy may already hold ids remapped by another handler (see extended branch)
        $sourceObjects = $this->getNodes(new JsonObject($matchSource), $rule->getPropertyPath());
        $usesSource = count($sourceObjects) === count($objects);
        if (!$usesSource) {
            $this->getLogger()->notice("Source and translation differ in structure for path={$rule->getPropertyPath()}, using translated values");
        }
        $changed = false;
        foreach ($objects as $index => $node) {
            $ref = &$node->getValue();
            $original = $usesSource ? $sourceObjects[$index]->getValue() : $ref;
            if (!is_numeric($original) || (int)$original <= 0) {
                unset($ref);
                continue;
            }
            $replaced = $replacer->processAttributeOnDownload($original, $original, $submission);
            if ($replaced !== $original) {
                $ref = $replaced;
                $changed = true;
            }
            unset($ref);
        }
        return $changed;
    }

    /**
     * @return JsonObject[]
     */
    private function getNodes(JsonObject $jsonObject, string $path): array
    {
        $objects = $jsonObject->getJsonObjects($path);
        if ($objects === false || $objects === null) {
            return [];
        }

        return is_array($objects) ? array_values($objects) : [$objects];
    }

    /**
     * @return array{0:string,1:string} [replacerId, contentTypeHint]
     */
    private function parseReplacer(string $replacerId): array
    {
        $parts = explode('|', $replacerId, 2);
        return [$parts[0], $parts[1] ?? ''];
    }

    /**
     * @param array<int, string|int> $segments
     */
    private function buildLeafKey(string $metaKey, array $segments): string
    {
        return $metaKey . '|' . $this->matcher->pathToString($segments);
    }

    private function buildKey(string $metaKey, string $path, int $index): string
    {
        return $metaKey . '|' . $path . '|' . $index;
    }
}
