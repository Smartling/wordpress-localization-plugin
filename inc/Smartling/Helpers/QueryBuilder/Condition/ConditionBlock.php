<?php

namespace Smartling\Helpers\QueryBuilder\Condition;

class ConditionBlock
{
    /**
     * @var Condition[]
     */
    private $conditions = [];

    /**
     * @var ConditionBlock[]
     */
    private $blocks = [];

    /**
     * @var string
     */
    private $operator;

    public function __construct(string $conditionOperator)
    {
        if (!$this->validateOperator($conditionOperator)) {
            throw new \InvalidArgumentException('Invalid operator');
        }

        $this->operator = vsprintf(' %s ', [$conditionOperator]);
    }

    private function validateOperator(string $operator): bool
    {
        $validOperators = [
            ConditionBuilder::CONDITION_BLOCK_LEVEL_OPERATOR_OR,
            ConditionBuilder::CONDITION_BLOCK_LEVEL_OPERATOR_AND,
        ];

        return in_array($operator, $validOperators, true);
    }

    public static function getConditionBlock(
        string $conditionOperator = ConditionBuilder::CONDITION_BLOCK_LEVEL_OPERATOR_AND
    ): ConditionBlock
    {
        return new self($conditionOperator);
    }

    public function addCondition(Condition $condition): void
    {
        $this->conditions[] = $condition;
    }

    public function hasConditions(): bool
    {
        return count($this->conditions) > 0;
    }

    public function addConditionBlock(ConditionBlock $block): void
    {
        $this->blocks[] = $block;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        $preRendered = [];

        foreach ($this->conditions as $condition) {
            $preRendered[] = (string)$condition;
        }

        foreach ($this->blocks as $block) {
            $preRendered[] = (string)$block;
        }

        return vsprintf('( %s )', [implode($this->operator, $preRendered)]);
    }
}
