<?php

namespace Smartling\Tuner;

use Smartling\WP\WPHookInterface;

class FilterManager extends CustomizationManagerAbstract implements WPHookInterface
{

    const STORAGE_KEY = 'CUSTOM_FILTERS';

    public function __construct()
    {
        parent::__construct(static::STORAGE_KEY);
    }

    /**
     * @param $items []
     *
     * @return array
     */
    public function injector($items)
    {
        $this->loadData();
        $list = static::listItems();

        if (is_array($list) && 0 < count($list)) {
            return array_merge($items, $list);
        }

        return $items;
    }

    public function register()
    {
        add_filter('smartling_register_field_filter', [$this, 'injector']);
    }
}