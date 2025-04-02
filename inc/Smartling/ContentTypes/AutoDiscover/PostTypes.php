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

    public function hookHandler(): void
    {
        global $wp_post_types;
        foreach ($wp_post_types as $postTypeName => $postType) {
            if (in_array($postTypeName, $this->ignoredTypes, true)) {
                continue;
            }
            add_action(ExportedAPI::FILTER_SMARTLING_REGISTER_CUSTOM_POST_TYPE, static function (array $definition) use ($postType, $postTypeName) {
                if ($postType->public ?? false) {
                    return array_merge($definition, [
                        [
                            "type" =>
                                [
                                    'identifier' => $postTypeName,
                                    'widget' => [
                                        'visible' => true,
                                    ],
                                    'visibility' => [
                                        'submissionBoard' => true,
                                        'bulkSubmit' => true,
                                    ],
                                ],
                        ],
                    ]);
                }

                return $definition;
            }, 0);
        }
    }

    public function registerHookHandler(): void
    {
        add_action('wp_loaded', [$this, 'hookHandler']);
    }
}
