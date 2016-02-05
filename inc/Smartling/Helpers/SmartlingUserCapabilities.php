<?php

namespace Smartling\Helpers;

/**
 * Class SmartlingUserCapabilities
 * @package Smartling\Helpers
 */
class SmartlingUserCapabilities
{
    const SMARTLING_CAPABILITY_MENU_CAP = 'smartling_connector_menu_cap';

    const SMARTLING_CAPABILITY_PROFILE_CAP = 'smartling_connector_profile_cap';

    const SMARTLING_CAPABILITY_WIDGET_CAP = 'smartling_connector_widget_cap';

    public static $CAPABILITY = [
        self::SMARTLING_CAPABILITY_WIDGET_CAP,
        self::SMARTLING_CAPABILITY_PROFILE_CAP,
        self::SMARTLING_CAPABILITY_MENU_CAP,
    ];
}