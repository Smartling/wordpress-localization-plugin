<?php

namespace Smartling\DbAl;

use Smartling\Helpers\QueryBuilder\Condition\Condition;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBlock;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBuilder;
use Smartling\Helpers\QueryBuilder\QueryBuilder;

class MultilingualPressConnector
{
    public function getEnglishNameFromMlpLanguagesTable(string $locale, string $dbField, $wpdb): string
    {
        $condition = ConditionBlock::getConditionBlock();
        $condition->addCondition(Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ, $dbField, [$locale]));
        $query = QueryBuilder::buildSelectQuery($wpdb->base_prefix . 'mlp_languages', ['english_name'], $condition, [], ['page' => 1, 'limit' => 1]);

        return $wpdb->get_results($query, ARRAY_A)[0]['english_name'] ?? $locale;
    }
}
