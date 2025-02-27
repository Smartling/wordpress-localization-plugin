<?php

namespace Smartling\Tuner;

use Smartling\WP\WPHookInterface;

class FilterManager extends CustomizationManagerAbstract implements WPHookInterface
{
    private const string STORAGE_KEY = 'CUSTOM_FILTERS';

    public function __construct()
    {
        parent::__construct(static::STORAGE_KEY);
    }

    public function injector(array $items): array
    {
        $this->loadData();
        $list = $this->listItems();

        if (0 < count($list)) {
            return array_merge($items, $list);
        }

        return $items;
    }

    public function register(): void
    {
        add_filter('smartling_register_field_filter', [$this, 'injector']);
    }
}
