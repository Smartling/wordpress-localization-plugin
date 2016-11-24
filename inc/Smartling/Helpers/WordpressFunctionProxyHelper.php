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
}