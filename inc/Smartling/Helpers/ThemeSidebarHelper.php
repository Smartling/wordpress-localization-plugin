<?php

namespace Smartling\Helpers;

/**
 * Class ThemeSidebarHelper
 *
 * @package Smartling\Helpers
 */
class ThemeSidebarHelper
{

    const string INACTIVE_BAR_ID = 'wp_inactive_widgets';

    /**
     * @return array
     */
    public static function getSideBarsIds()
    {
        global $wp_registered_sidebars;

        return array_keys($wp_registered_sidebars);
    }

    /**
     * @param        $sideBarId
     * @param string $default
     *
     * @return string
     */
    public static function getSideBarLabel($sideBarId, $default = '')
    {
        if ($sideBarId == self::INACTIVE_BAR_ID) {
            return 'Inactive Widgets';
        }

        global $wp_registered_sidebars;

        if (array_key_exists($sideBarId, $wp_registered_sidebars)) {
            return $wp_registered_sidebars[$sideBarId]['name'];
        } else {
            return $default;
        }
    }


}
