<?php

namespace Smartling\Tuner;

use Smartling\WP\WPHookInterface;

class ShortcodeManager extends CustomizationManagerAbstract implements WPHookInterface
{
    private const STORAGE_KEY = 'CUSTOM_SHORTCODES';

    public function __construct()
    {
        parent::__construct(static::STORAGE_KEY);
    }

    public function injector(array $items): array
    {
        $this->loadData();
        $shortcodes = [];
        foreach ($this->listItems() as $item) {
            $shortcodes[] = $item['name'];
        }

        return array_merge($items, $shortcodes);
    }

    public function register(): void
    {
        add_filter('smartling_inject_shortcode', [$this, 'injector']);
    }
}
