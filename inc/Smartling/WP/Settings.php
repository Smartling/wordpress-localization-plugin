<?php
/**
 * Created by PhpStorm.
 * User: sergey@slepokurov.com
 * Date: 20.01.2015
 * Time: 12:01
 */
namespace Smartling\WP;

use Smartling\WP\WPHookInterface;

class Settings extends WPAbstract implements WPHookInterface {

    public function register() {
        wp_enqueue_style( $this->getPluginInfo()->getName(), $this->getPluginInfo()->getUrl() . '/css/smartling-connector-admin.css', array(), $this->getPluginInfo()->getVersion(), 'all' );
        wp_enqueue_script( $this->getPluginInfo()->getName(), $this->getPluginInfo()->getUrl() . '/js/smartling-connector-admin.js', array( 'jquery' ),  $this->getPluginInfo()->getVersion(), false );
        add_action('admin_menu', array($this, 'menu'));
        add_action('admin_post_smartling_settings', array($this, 'save'));
    }

    public function menu() {
        add_menu_page( 'Smartling Connector', 'Smartling Connector', 'Administrator', 'smartling-settings', array( $this, 'view')  );
    }

    public function getLocales(){
        return array();
    }

    public function save() {

    }
}