<?php

/** Want this not to change whenever underlying WP functions change
 * @noinspection PhpMethodParametersCountMismatchInspection
 * @noinspection PhpMissingReturnTypeInspection
 * @noinspection PhpVoidFunctionResultUsedInspection
 * @noinspection ReturnTypeCanBeDeclaredInspection
 */

namespace Smartling\Helpers;

class WordpressFunctionProxyHelper
{
    public function add_action()
    {
        return add_action(...func_get_args());
    }
    public function get_home_url()
    {
        return get_home_url(...func_get_args());
    }
    public static function getPostTypes()
    {
        return get_post_types(...func_get_args());
    }

    /** @noinspection PhpUnused, used in plugin-info.yaml */
    public function plugin_dir_url()
    {
        return plugin_dir_url(...func_get_args());
    }

    public function get_post_type()
    {
        return get_post_type(...func_get_args());
    }

    public function get_post_types()
    {
        return get_post_types(...func_get_args());
    }

    public static function getTaxonomies()
    {
        return get_taxonomies(...func_get_args());
    }

    public function get_current_blog_id()
    {
        return get_current_blog_id(...func_get_args());
    }

    public function get_current_user_id()
    {
        return get_current_user_id(...func_get_args());
    }

    public function get_taxonomies()
    {
        return get_taxonomies(...func_get_args());
    }

    public function get_term_meta()
    {
        return get_term_meta(...func_get_args());
    }

    public function getPostMeta()
    {
        return get_post_meta(...func_get_args());
    }

    public function is_plugin_active(string $plugin): bool
    {
        // This function is unavailable before all_plugins_loaded is called, and it's safe to assume that plugins are not "active" when function doesn't exist
        if (function_exists('is_plugin_active')) {
            return is_plugin_active($plugin);
        }

        return false;
    }

    /**
     * @return mixed
     */
    public function apply_filters()
    {
        return apply_filters(...func_get_args());
    }

    public function delete_post_meta()
    {
        return delete_post_meta(...func_get_args());
    }

    public function get_blog_permalink(int $blogId, int $postId)
    {
        return get_blog_permalink(...func_get_args());
    }

    /**
     * @return \WP_Term[]|\WP_Error
     */
    public function getObjectTerms(int $objectId): array|\WP_Error
    {
        return wp_get_object_terms($objectId, get_taxonomies());
    }

    public function get_permalink()
    {
        return get_permalink(...func_get_args());
    }

    public function get_plugins(): array
    {
        if (function_exists('get_plugins')) {
            return get_plugins(...func_get_args());
        }

        return [];
    }

    public function get_post()
    {
        return get_post(...func_get_args());
    }

    public function get_posts()
    {
        return get_posts(...func_get_args());
    }

    public function get_terms()
    {
        return get_terms(...func_get_args());
    }

    public function maybe_unserialize()
    {
        return maybe_unserialize(...func_get_args());
    }

    /**
     * @param array|\WP_Block_Parser_Block $block
     * @return string
     */
    public function serialize_block($block): string
    {
        return serialize_block($block);
    }

    public function setObjectTerms(int $objectId, array $termIds, string $taxonomy): array|\WP_Error
    {
        return wp_set_object_terms($objectId, $termIds, $taxonomy);
    }

    public function url_to_postid(string $url): int
    {
        return url_to_postid($url);
    }

    public function wp_get_active_network_plugins(): array
    {
        return wp_get_active_network_plugins(...func_get_args());
    }

    public function wp_get_current_user()
    {
        return wp_get_current_user(...func_get_args());
    }

    public function wp_send_json()
    {
        return wp_send_json(...func_get_args());
    }

    public function wp_send_json_error()
    {
        return wp_send_json_error(...func_get_args());
    }

    public function wp_set_current_user(int $id, string $name = '')
    {
        return wp_set_current_user($id, $name);
    }
}
