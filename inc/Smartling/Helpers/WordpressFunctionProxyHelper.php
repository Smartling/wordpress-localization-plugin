<?php

namespace Smartling\Helpers;

class WordpressFunctionProxyHelper
{
    /**
     * Proxy for 'get_post_types' function
     * @return mixed
     */
    public static function getPostTypes()
    {
        return call_user_func_array('get_post_types', func_get_args());
    }

    public static function getTaxonomies()
    {
        return call_user_func_array('get_taxonomies', func_get_args());
    }

    public function getPostMeta($postId)
    {
        return get_post_meta($postId);
    }

    public function apply_filters()
    {
        return call_user_func_array('apply_filters', func_get_args());
    }
}
