<?php

namespace Smartling\Tuner;

class JsonFieldRulesManager extends CustomizationManagerAbstract
{
    public const STORAGE_KEY = 'CUSTOM_JSON_FIELD_RULES';

    public function __construct()
    {
        parent::__construct(self::STORAGE_KEY);
    }

    public function add(array $value): string
    {
        /** @noinspection TypeUnsafeArraySearchInspection comparing arrays, key order irrelevant */
        if (in_array($value, $this->state)) {
            return '';
        }
        do {
            $id = uniqid('', true);
        } while (array_key_exists($id, $this->state));
        $this->state[$id] = $value;
        return $id;
    }

    /**
     * @return JsonFieldRule[]
     */
    public function listItems(): array
    {
        $result = [];
        foreach (parent::listItems() as $id => $item) {
            try {
                $result[$id] = JsonFieldRule::fromArray($item);
            } catch (\InvalidArgumentException) {
                // skip malformed entries
            }
        }
        return $result;
    }
}
