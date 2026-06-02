<?php

namespace Smartling\Tuner;

use Smartling\Helpers\LoggerSafeTrait;

class JsonFieldRulesManager extends CustomizationManagerAbstract
{
    use LoggerSafeTrait;

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
                $this->getLogger()->debug("Unparsable json field rule $id, skipping");
            }
        }
        return $result;
    }
}
