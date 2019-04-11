<?php
/**
 * Plugin Name:       Exec Plugin
 * Version:           0.0.1
 * License:           GPL-2.0+
 * Network:           true
 */

add_action('plugins_loaded', function () {
    if (class_exists('Smartling\Bootstrap', false)) {
        add_action('exec_plugin_execute_hook', function () {
            $filename = \Smartling\Helpers\SimpleStorageHelper::get('execFile', null);
            if (!is_null($filename) && file_exists($filename) && is_file($filename) && is_readable($filename)) {
                \Smartling\Helpers\SimpleStorageHelper::drop('execFile');
                var_dump($filename);
                require_once $filename;
            }
        });
        wp_schedule_single_event(time() + 1, 'exec_plugin_execute_hook');
    }
}, 999);

