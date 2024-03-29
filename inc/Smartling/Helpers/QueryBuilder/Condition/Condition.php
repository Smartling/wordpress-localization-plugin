<?php

namespace Smartling\Helpers\QueryBuilder\Condition;

use Smartling\Helpers\QueryBuilder\QueryBuilder;

class Condition
{
    private string $condition;
    private string $field;
    private array $values;

    public function __construct(string $condition, string $field, array|float|int|string $values, bool $escapeField = true)
    {
        $this->condition = $condition;
        $this->field = $escapeField ? QueryBuilder::escapeName($field) : $field;
        if (!is_array($values)) {
            $values = [$values];
        }
        $this->values = QueryBuilder::escapeValues($values);
    }

    /**
     * @deprecated Call new Condition() instead
     */
    public static function getCondition(string $condition, string $field, array $values, bool $escapeField = true): Condition
    {
        return new self($condition, $field, $values, $escapeField);
    }

    public function __toString(): string
    {
        return ConditionBuilder::buildBlock($this->condition, array_merge([$this->field], $this->values));
    }

    public function getOperand(): string {
        return $this->condition;
    }

    public function getField(): string {
        return $this->field;
    }

    public function getValues(): array {
        return $this->values;
    }
}
