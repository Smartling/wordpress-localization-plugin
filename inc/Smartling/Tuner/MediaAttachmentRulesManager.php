<?php

namespace Smartling\Tuner;

use Smartling\Helpers\GutenbergReplacementRule;

class MediaAttachmentRulesManager extends CustomizationManagerAbstract
{
    private const STORAGE_KEY = 'CUSTOM_MEDIA_RULES';
    private array $preconfiguredRules;

    /**
     * @param GutenbergReplacementRule[] $rules
     */
    public function __construct(array $rules = [])
    {
        parent::__construct(static::STORAGE_KEY);
        $this->preconfiguredRules = $rules;
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
    public function getGutenbergReplacementRules(?string $blockType = null, ?string $attribute = null): array
    {
        $this->loadData();
        $rules = array_merge($this->preconfiguredRules, $this->listItems());
        if ($blockType !== null) {
            $rules = array_filter($rules, static function ($item) use ($blockType) {
                return $item->getBlockType() === $blockType;
            });
        }
        if ($attribute !== null) {
            $rules = array_filter($rules, static function ($item) use ($attribute) {
                return $item->getPropertyPath() === $attribute;
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
            'replacerId' => $item['replacerId'] ?? 'related|attachment'
        ];
    }
}
