<?php

namespace Smartling\ContentTypes\AutoDiscover;

use Smartling\Base\ExportedAPI;

class PostTypes
{
    private array $ignoredTypes;

    public function __construct(array $ignoredTypes)
    {
        $this->ignoredTypes = $ignoredTypes;
    }

    public function hookHandler($postType): void
    {
        if (in_array($postType, $this->ignoredTypes, true)) {
            return;
        }

        add_action(ExportedAPI::FILTER_SMARTLING_REGISTER_CUSTOM_POST_TYPE, static function (array $definition) use ($postType) {
            global $wp_post_types;

            if (true === $wp_post_types[$postType]->public) {
                return array_merge($definition, [
                    [
                        "type" =>
                            [
                                'identifier' => $postType,
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
            }

            return $definition;
        }, 0, 1);
    }

    public function registerHookHandler(): void
    {
        add_action('registered_post_type', [$this, 'hookHandler']);
    }
}
