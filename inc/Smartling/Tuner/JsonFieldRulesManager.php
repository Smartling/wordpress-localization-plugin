<?php

namespace Smartling\Tuner;

use Smartling\Helpers\LoggerSafeTrait;

class JsonFieldRulesManager extends CustomizationManagerAbstract
{
    use LoggerSafeTrait;

    public const STORAGE_KEY = 'CUSTOM_JSON_FIELD_RULES';
    public const EXPORT_FORMAT_VERSION = 1;

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
                $this->getLogger()->debug("Unparsable json field rule $id, skipping");
            }
        }
        return $result;
    }

    /**
     * Portable representation: rule ids are internal and intentionally left out
     *
     * @return array{version: int, rules: array<int, array>}
     */
    public function export(): array
    {
        return [
            'version' => self::EXPORT_FORMAT_VERSION,
            'rules' => array_values(array_map(
                static fn(JsonFieldRule $rule): array => $rule->toArray(),
                $this->listItems(),
            )),
        ];
    }

    /**
     * Additive only: existing rules are never modified or removed, rules identical to an existing one are skipped.
     * Does not persist, call saveData() afterwards.
     *
     * @param JsonFieldRule[] $rules
     * @return array{added: int, skipped: int}
     */
    public function import(array $rules): array
    {
        $added = 0;
        $skipped = 0;
        $known = array_map(static fn(JsonFieldRule $rule): array => $rule->toArray(), array_values($this->listItems()));
        foreach ($rules as $rule) {
            $data = $rule->toArray();
            /** @noinspection TypeUnsafeArraySearchInspection comparing arrays, key order irrelevant */
            if (in_array($data, $known) || $this->add($data) === '') {
                $skipped++;
                continue;
            }
            $known[] = $data;
            $added++;
        }

        return ['added' => $added, 'skipped' => $skipped];
    }
}
