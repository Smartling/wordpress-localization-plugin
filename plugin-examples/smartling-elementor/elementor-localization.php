<?php

use KPS3\Smartling\Elementor\Bootloader;

/**
 * @link https://www.kps3.com
 * @since 1.0.0
 * @package smartling-elementor
 * @wordpress-plugin
 * Plugin Name: Elementor localization
 * Description: Extend Smartling Connector functionality to support elementor. Initial development by KPS, maintained by Smartling
 * SupportedConnectorVersions: 2.6-2.7
 */

if ( ! class_exists(Bootloader::class)) {
    require_once plugin_dir_path(__FILE__) . 'src/Bootloader.php';
}

/**
 * Execute ONLY for admin pages
 */
if ((defined('DOING_CRON') && true === DOING_CRON) || is_admin()) {
    add_action('smartling_before_init', static function ($di) {
        Bootloader::boot(__FILE__, $di);
    });
}

if ( ! is_callable('smartling_elementor_json_string')) {
    function smartling_elementor_json_string($value)
    {
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                if (is_array($v)) {
                    $value[$k] = smartling_elementor_json_string($v);
                } else {
                    $value[$k] = json_encode($v, JSON_THROW_ON_ERROR);
                }
            }
        } else {
            $value = json_encode($value, JSON_THROW_ON_ERROR);
        }

        return $value;
    }
}
