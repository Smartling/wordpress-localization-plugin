<?php

namespace Smartling\Services;

use Smartling\Base\ExportedAPI;
use Smartling\Bootstrap;
use Smartling\ContentTypes\CustomPostType;
use Smartling\Helpers\LoggerSafeTrait;
use Smartling\WP\WPHookInterface;

class CustomPostTypeDebugService implements WPHookInterface {

    use LoggerSafeTrait;

    private const SF_PRESS_RELEASE = 'sf_press_release';

    public function register(): void
    {
        add_filter(ExportedAPI::FILTER_SMARTLING_REGISTER_CUSTOM_POST_TYPE, [$this, 'debugPostTypes']);
    }

    public function debugPostTypes(array $postTypes): array
    {
        $debugInfo = [];
        foreach ($postTypes as $postType) {
            $debugInfo[] = $postType['type']['identifier'] ?? '*invalid* (' . json_encode($postType) . ')';
        }
        $this->getLogger()->debug("Post types registered: " . implode(', ', $debugInfo));
        if (!in_array(self::SF_PRESS_RELEASE, $debugInfo, true)) {
            $this->getLogger()->debug(sprintf("No %s in registered post types", self::SF_PRESS_RELEASE));
            global $wp_post_types;
            if (array_key_exists(self::SF_PRESS_RELEASE, $wp_post_types)) {
                $this->getLogger()->debug("Post type exists in globals, but not registered");
            }
            $di = Bootstrap::getContainer();
            CustomPostType::registerCustomType($di, [
                "type" =>
                    [
                        'identifier' => self::SF_PRESS_RELEASE,
                        'widget'     => [
                            'visible' => true,
                        ],
                        'visibility' => [
                            'submissionBoard' => true,
                            'bulkSubmit' => true,
                        ],
                    ],
            ]);
        }
        return $postTypes;
    }
}
