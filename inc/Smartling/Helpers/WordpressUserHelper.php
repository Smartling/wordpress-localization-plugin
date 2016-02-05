<?php

namespace Smartling\Helpers;

/**
 * Class WordpressUserHelper
 *
 * @package Smartling\Helpers
 */
class WordpressUserHelper
{

    /**
     * @return string
     */
    public static function getUserLogin()
    {
        $ud = get_userdata(get_current_user_id());

        return $ud->user_login;
    }

    public static function getUserLoginById($id)
    {
        return get_the_author_meta('user_nicename', $id);
    }
}