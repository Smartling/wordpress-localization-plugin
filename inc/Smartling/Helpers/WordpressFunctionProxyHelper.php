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

    public function get_term_meta()
    {
        return get_term_meta(...func_get_args());
    }

    public function getPostMeta($postId)
    {
        return get_post_meta($postId);
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

    public function delete_term_meta()
    {
        return delete_term_meta(...func_get_args());
    }

    /**
     * @param int $blogId
     * @param int $postId
     * @return mixed
     */
    public function get_blog_permalink(int $blogId, int $postId)
    {
        return get_blog_permalink(...func_get_args());
    }

    public function get_terms()
    {
        return get_terms(...func_get_args());
    }

    /**
     * @param array|\WP_Block_Parser_Block $block
     * @return string
     */
    public function serialize_block($block): string
    {
        return serialize_block($block);
    }

    public function url_to_postid(string $url): int
    {
        return (int)url_to_postid($url);
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
