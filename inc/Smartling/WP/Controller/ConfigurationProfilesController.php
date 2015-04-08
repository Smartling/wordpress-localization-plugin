<?php

namespace Smartling\WP\Controller;

use Smartling\WP\WPAbstract;
use Smartling\WP\WPHookInterface;

/**
 * Class ConfigurationProfilesController
 *
 * @package Smartling\WP\Controller
 */
class ConfigurationProfilesController extends WPAbstract implements WPHookInterface
{

	public function wp_enqueue () {
		wp_enqueue_script(
			$this->getPluginInfo()->getName() . 'settings',
			$this->getPluginInfo()->getUrl() . 'js/smartling-connector-admin.js',
			array ( 'jquery' ),
			$this->getPluginInfo()->getVersion(),
			false
		);
		wp_register_style(
			$this->getPluginInfo()->getName(),
			$this->getPluginInfo()->getUrl() . 'css/smartling-connector-admin.css',
			array (),
			$this->getPluginInfo()->getVersion(),
			'all'
		);
		wp_enqueue_style( $this->getPluginInfo()->getName() );
	}

	/**
	 * Registers wp hook handlers. Invoked by wordpress.
	 *
	 * @return void
	 */
	public function register () {
		add_action( 'admin_enqueue_scripts', array ( $this, 'wp_enqueue' ) );

		add_action( 'admin_menu', array ( $this, 'menu' ) );
		add_action( 'network_admin_menu', array ( $this, 'menu' ) );

		add_action( 'admin_post_smartling_configuration_profile_edit', array ( $this, 'edit' ) );
		add_action( 'admin_post_smartling_configuration_profile_list', array ( $this, 'listProfiles' ) );


		add_action( 'admin_post_smartling_run_cron', array ( $this, 'run_cron' ) );

		add_action( 'admin_post_smartling_download_log_file', array ( $this, 'download_log' ) );
	}

	public function menu () {
		add_submenu_page(
			'smartling-submissions-page',
			'Configuration profiles',
			'Settings',
			'Administrator',
			'smartling_configuration_profile_list',
			array (
				$this,
				'view'
			)
		);
	}

	public function listProfiles()
	{
		$table = new SubmissionTableWidget( $this->getManager() );
		$this->view( $table );
	}

}