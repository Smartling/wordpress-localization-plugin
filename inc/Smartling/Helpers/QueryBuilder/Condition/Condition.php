<?php

namespace Smartling\Helpers\QueryBuilder\Condition;

use Smartling\Helpers\QueryBuilder\QueryBuilder;

class Condition
{
    private string $condition;
    private string $field;
    private array $values;

    protected function __construct(string $condition, string $field, array $values)
    {
        $this->condition = $condition;
        $this->field = QueryBuilder::escapeName($field);
        $this->values = QueryBuilder::escapeValues($values);
    }

    public static function getCondition(string $condition, string $field, array $values): Condition
    {
        return new self($condition, $field, $values);
    }

    public function __toString(): string
    {
        return ConditionBuilder::buildBlock($this->condition, array_merge([$this->field], $this->values));
    }
}
