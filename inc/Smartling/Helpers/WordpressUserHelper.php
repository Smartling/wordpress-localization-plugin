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
        $userId = get_current_user_id();
        if (0 === $userId) {
            return 'cron-job';
        }
        $ud = get_userdata($userId);
        if (false !== $ud) {
            return $ud->user_login;
        } else {
            return 'unknown';
        }
    }

    public static function getUserLoginById($id)
    {
        return get_the_author_meta('user_nicename', $id);
    }
}