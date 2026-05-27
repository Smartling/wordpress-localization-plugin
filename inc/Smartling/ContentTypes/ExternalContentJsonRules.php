<?php

namespace Smartling\ContentTypes;

use Smartling\Extensions\Pluggable;
use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Replacers\ReplacerFactory;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Tuner\JsonFieldRule;
use Smartling\Tuner\JsonFieldRulesManager;
use Smartling\Vendor\JsonPath\JsonObject;

class ExternalContentJsonRules implements ContentTypeModifyingInterface
{
    use LoggerSafeTrait;

    public const PLUGIN_ID = 'json-rules';

    public function __construct(
        private JsonFieldRulesManager $rulesManager,
        private ReplacerFactory $replacerFactory,
        private WordpressFunctionProxyHelper $wpProxy,
    ) {
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
        foreach ($this->rulesManager->listItems() as $rule) {
            if ($rule->getContentType() === '*' || $rule->getContentType() === $contentType) {
                return Pluggable::SUPPORTED;
            }
        }
        return Pluggable::NOT_SUPPORTED;
    }

    public function getExternalContentTypes(): array
    {
        return [];
    }

    public function getContentFields(SubmissionEntity $submission, bool $raw): array
    {
        $result = [];
        $this->rulesManager->loadData();
        foreach ($this->getRulesByMetaKey($submission->getContentType()) as $metaKey => $rules) {
            $json = $this->readMetaJson($submission->getSourceId(), $metaKey);
            if ($json === null) {
                continue;
            }
            foreach ($rules as $rule) {
                if ($this->parseReplacer($rule->getReplacerId())[0] !== ReplacerFactory::REPLACER_TRANSLATE) {
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

    public function getRelatedContent(string $contentType, int $contentId): array
    {
        $result = [];
        $this->rulesManager->loadData();
        foreach ($this->getRulesByMetaKey($contentType) as $metaKey => $rules) {
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
                foreach ($this->safeGet($json, $rule->getPropertyPath()) as $value) {
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
        foreach ($this->getRulesByMetaKey($submission->getContentType()) as $metaKey => $rules) {
            $sourceJson = $original['meta'][$metaKey] ?? null;
            if (!is_string($sourceJson) || $sourceJson === '') {
                continue;
            }
            try {
                $jsonObject = new JsonObject($sourceJson);
            } catch (\Throwable $e) {
                $this->getLogger()->debug("Failed to parse meta $metaKey as JSON: " . $e->getMessage());
                continue;
            }
            $modified = false;
            foreach ($rules as $rule) {
                [$replacer] = $this->parseReplacer($rule->getReplacerId());
                if ($replacer === ReplacerFactory::REPLACER_TRANSLATE) {
                    $modified = $this->applyTranslateRule($jsonObject, $rule, $metaKey, $translations) || $modified;
                } elseif ($replacer === ReplacerFactory::REPLACER_RELATED) {
                    $modified = $this->applyRelatedRule($jsonObject, $rule, $submission) || $modified;
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
        foreach (array_keys($this->getRulesByMetaKey($submission->getContentType())) as $metaKey) {
            if (isset($source['meta'][$metaKey])) {
                unset($source['meta'][$metaKey]);
            }
        }
        return $source;
    }

    /**
     * @return array<string, JsonFieldRule[]>
     */
    private function getRulesByMetaKey(string $contentType): array
    {
        $result = [];
        foreach ($this->rulesManager->listItems() as $rule) {
            if ($rule->getContentType() !== '*' && $rule->getContentType() !== $contentType) {
                continue;
            }
            $result[$rule->getMetaKey()][] = $rule;
        }
        return $result;
    }

    private function readMetaJson(int $contentId, string $metaKey): ?string
    {
        $value = $this->wpProxy->getPostMeta($contentId, $metaKey, true);
        if (!is_string($value) || $value === '') {
            return null;
        }
        try {
            json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        return $value;
    }

    private function safeGet(string $json, string $path): array
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

    private function applyTranslateRule(JsonObject $jsonObject, JsonFieldRule $rule, string $metaKey, array $translations): bool
    {
        $objects = $jsonObject->getJsonObjects($rule->getPropertyPath());
        if ($objects === false) {
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

    private function applyRelatedRule(JsonObject $jsonObject, JsonFieldRule $rule, SubmissionEntity $submission): bool
    {
        try {
            $replacer = $this->replacerFactory->getReplacer($rule->getReplacerId());
        } catch (\Throwable $e) {
            $this->getLogger()->notice("Unable to resolve replacer {$rule->getReplacerId()}: " . $e->getMessage());
            return false;
        }
        $objects = $jsonObject->getJsonObjects($rule->getPropertyPath());
        if ($objects === false) {
            return false;
        }
        if (!is_array($objects)) {
            $objects = [$objects];
        }
        $changed = false;
        foreach ($objects as $node) {
            $ref = &$node->getValue();
            $original = $ref;
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
     * @return array{0:string,1:string} [replacerId, contentTypeHint]
     */
    private function parseReplacer(string $replacerId): array
    {
        $parts = explode('|', $replacerId, 2);
        return [$parts[0], $parts[1] ?? ''];
    }

    private function buildKey(string $metaKey, string $path, int $index): string
    {
        return $metaKey . '|' . $path . '|' . $index;
    }
}
