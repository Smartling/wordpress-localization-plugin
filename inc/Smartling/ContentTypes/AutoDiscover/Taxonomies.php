<?php

namespace Smartling\ContentTypes\AutoDiscover;

class Taxonomies
{
    private array $ignoredTypes;

    public function __construct(array $ignoredTypes)
    {
        $this->ignoredTypes = $ignoredTypes;
    }

    public function hookHandler($taxonomy): void
    {
        if (in_array($taxonomy, $this->ignoredTypes, true)) {
            return;
        }

        add_action('smartling_register_custom_taxonomy', static function (array $definition) use ($taxonomy) {
            return array_merge(
                $definition,
                [
                    [
                        'taxonomy' => [
                            'identifier' => $taxonomy,
                            'widget'     => [
                                'visible' => true,
                            ],
                            'visibility' => [
                                'submissionBoard' => true,
                                'bulkSubmit'      => true,
                            ],
                        ],
                    ],
                ]);
        }, 0, 1);
    }

    public function registerHookHandler(): void
    {
        add_action('registered_taxonomy', [$this, 'hookHandler']);
    }
}
