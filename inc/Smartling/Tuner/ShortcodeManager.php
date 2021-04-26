<?php

namespace Smartling\Tuner;

use Smartling\WP\WPHookInterface;

class ShortcodeManager extends CustomizationManagerAbstract implements WPHookInterface
{

    const STORAGE_KEY = 'CUSTOM_SHORTCODES';

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
        $shortcodes = [];
        foreach ($list as $item) {
            $shortcodes[] = $item['name'];
        }

        return array_merge($items, $shortcodes);
    }

    public function register(): void
    {
        add_filter('smartling_inject_shortcode', [$this, 'injector']);
    }
}
