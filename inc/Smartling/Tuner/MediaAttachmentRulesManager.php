<?php

namespace Smartling\Tuner;

use Smartling\Helpers\GutenbergReplacementRule;
use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Vendor\JsonPath\JsonObject;

class MediaAttachmentRulesManager extends CustomizationManagerAbstract
{
    use LoggerSafeTrait;

    private const string STORAGE_KEY = 'CUSTOM_MEDIA_RULES';
    private array $preconfiguredRules;
    private array $temporaryRules = [];

    /**
     * @param GutenbergReplacementRule[] $rules
     */
    public function __construct(array $rules = [])
    {
        parent::__construct(static::STORAGE_KEY);
        $this->preconfiguredRules = $rules;
    }

    public function add(array $value): string
    {
        /** @noinspection TypeUnsafeArraySearchInspection we're comparing arrays, and don't care about key order here */
        if (in_array($value, $this->state)) {
            return '';
        }
        return parent::add($value);
    }

    public function addTemporaryRule(GutenbergReplacementRule $rule): void
    {
        $this->temporaryRules[$rule->getBlockType() . $rule->getPropertyPath()] = $rule;
    }

    /**
     * @return GutenbergReplacementRule[]
     */
    public function getPreconfiguredRules(): array {
        return $this->preconfiguredRules;
    }

    /**
     * @return GutenbergReplacementRule[]
     */
    public function getGutenbergReplacementRules(?string $blockType = null, array $attributes = []): array
    {
        $this->loadData();
        $rules = array_merge($this->preconfiguredRules, $this->temporaryRules, $this->listItems());
        if ($blockType !== null) {
            $rules = array_filter($rules, static function ($item) use ($blockType) {
                return preg_match('#' . preg_replace('~([^\\\\])#~', '\1\#', $item->getBlockType()) . '#', $blockType) === 1;
            });
        }
        if (count($attributes) > 0) {
            try {
                $json = json_encode($attributes, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                $this->getLogger()->notice(sprintf('Failed to encode attributes attributeCount=%d, blockName="%s": %s', count($json), $blockType, $e->getMessage()));
                return [];
            }
            $rules = array_filter($rules, function ($item) use ($attributes, $json) {
                if ($this->isJsonPath($item->getPropertyPath())) {
                    return $this->jsonValueExists($json, $item->getPropertyPath());
                }
                foreach (array_keys($attributes) as $attribute) {
                    if (preg_match('#' . preg_replace('~([^\\\\])#~', '\1\#', $item->getPropertyPath()) . '#', $attribute) === 1) {
                        return true;
                    }
                }
                return false;
            });
        }

        return $rules;
    }

    /**
     * @return GutenbergReplacementRule[]
     */
    public function listItems(): array
    {
        $result = [];
        $this->loadData();
        $state = parent::listItems();
        foreach ($state as $id => $item) {
            $item = $this->withDefaults($item);
            $result[$id] = new GutenbergReplacementRule(
                $item['blockType'],
                $item['propertyPath'],
                $item['replacerId'],
            );
        }

        return $result;
    }

    private function withDefaults(array $item): array
    {
        // initial version was ['block' => 'string', 'path' => 'string', 'contentType' => 'string']
        return [
            'blockType' => $item['block'],
            'propertyPath' => $item['path'],
            'replacerId' => $item['replacerId'] ?? 'related|postbased'
        ];
    }

    public function isJsonPath(string $string): bool
    {
        return preg_match('~^\$[.\[]~', $string) === 1;
    }

    private function jsonValueExists(string $json, string $path): bool
    {
        try {
            $value = (new JsonObject($json))->get($path);
        } catch (\Exception $e) {
            $this->getLogger()->debug('Got invalid json when trying to check if json value exists (' . $e->getMessage() . '), skipping');
            return false;
        }
        return $value !== false && count($value) > 0;
    }
}
