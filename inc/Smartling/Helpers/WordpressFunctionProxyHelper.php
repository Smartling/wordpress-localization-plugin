<?php

namespace Smartling\Helpers;

class WordpressFunctionProxyHelper
{
    public static function getPostTypes()
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

    public function get_taxonomies()
    {
        return get_taxonomies(...func_get_args());
    }

    public function getPostMeta($postId)
    {
        return get_post_meta($postId);
    }

    public function apply_filters()
    {
        return apply_filters(...func_get_args());
    }

    public function delete_post_meta()
    {
        return delete_post_meta(...func_get_args());
    }

    public function delete_term_meta()
    {
        return delete_term_meta(...func_get_args());
    }

    public function get_terms()
    {
        return get_terms(...func_get_args());
    }

    public function wp_send_json()
    {
        return wp_send_json(...func_get_args());
    }

    public function wp_send_json_error()
    {
        return wp_send_json_error(...func_get_args());
    }
}
