<?php

namespace Smartling\WP\Controller;

use Smartling\Bootstrap;
use Smartling\Settings\Locale;
use Smartling\Settings\TargetLocale;
use Smartling\WP\JobEngine;
use Smartling\WP\WPAbstract;
use Smartling\WP\WPHookInterface;

class ConfigurationProfileFormController extends WPAbstract implements WPHookInterface {

	public function wp_enqueue () {
		wp_enqueue_script(
			$this->getPluginInfo()->getName() . "settings",
			$this->getPluginInfo()->getUrl() . 'js/smartling-connector-admin.js',
			[ 'jquery' ],
			$this->getPluginInfo()->getVersion(),
			false
		);
		wp_register_style(
			$this->getPluginInfo()->getName(),
			$this->getPluginInfo()->getUrl() . 'css/smartling-connector-admin.css',
			[ ],
			$this->getPluginInfo()->getVersion(),
			'all'
		);
		wp_enqueue_style( $this->getPluginInfo()->getName() );
	}

	public function register () {
		add_action( 'admin_enqueue_scripts', [ $this, 'wp_enqueue' ] );
		add_action( 'admin_menu', [ $this, 'menu' ] );
		add_action( 'network_admin_menu', [ $this, 'menu' ] );
		add_action( 'admin_post_smartling_configuration_profile_save', [ $this, 'save' ] );
		add_action( 'admin_post_smartling_run_cron', [ $this, 'run_cron' ] );
		add_action( 'admin_post_smartling_download_log_file', [ $this, 'download_log' ] );
	}

	public function menu () {
		add_submenu_page(
			'smartling_configuration_profile_list',
			'Profile setup',
			'Configuration Profile Setup',
			'Administrator',
			'smartling_configuration_profile_setup',
			[
				$this,
				'edit',
			]
		);
	}


	public function edit () {
		$this->view( $this->getEntityHelper()->getSettingsManager() );
	}

	public function save () {
		$settings = &$_REQUEST['smartling_settings'];

		$newApiKey = &$_REQUEST['apiKey'];

		$profileId = (int) ( $settings['id'] ? : 0 );

		$settingsManager = $this->getEntityHelper()->getSettingsManager();

		if ( 0 === $profileId ) {
			$profile = $settingsManager->createProfile( [ ] );
		} else {
			$profiles = $settingsManager->getEntityById( $profileId );

			/**
			 * @var ConfigurationProfileEntity $profile
			 */
			$profile = reset( $profiles );
		}

		if ( array_key_exists( 'profileName', $settings ) ) {
			$profile->setProfileName( $settings['profileName'] );
		}


		if ( array_key_exists( 'active', $settings ) ) {
			$profile->setIsActive( $settings['active'] );
		}


		if ( array_key_exists( 'apiUrl', $settings ) ) {
			$profile->setApiUrl( $settings['apiUrl'] );
		}

		if ( array_key_exists( 'projectId', $settings ) ) {
			$profile->setProjectId( $settings['projectId'] );
		}

		if ( null !== $newApiKey ) {
			$profile->setApiKey( $newApiKey );
		}

		if ( array_key_exists( 'retrievalType', $settings ) ) {
			$profile->setRetrievalType( $settings['retrievalType'] );
		}

		if ( array_key_exists( 'callbackUrl', $settings ) ) {
			$profile->setCallBackUrl( $settings['callbackUrl'] === 'on' );
		}

		if ( array_key_exists( 'autoAuthorize', $settings ) ) {
			$profile->setAutoAuthorize( 'on' === $settings['autoAuthorize'] );
		} else {
			$profile->setAutoAuthorize( false );
		}

		if ( array_key_exists( 'defaultLocale', $settings ) ) {
			$defaultBlogId = (int) $settings['defaultLocale'];

			$locale = new Locale();
			$locale->setBlogId( $defaultBlogId );
			$locale->setLabel(
				$this->getEntityHelper()->getSiteHelper()->getBlogLabelById(
					$this->getEntityHelper()->getConnector(),
					$defaultBlogId
				)
			);

			$profile->setOriginalBlogId( $locale );


			//$targetLocales->setDefaultBlog( $defaultBlogId );
			//$targetLocales->setDefaultLocale( $targetLocales->getTargetLocaleLabel( $defaultBlogId ) );
		}

		if ( array_key_exists( 'targetLocales', $settings ) ) {
			$locales = [ ];

			foreach ( $settings['targetLocales'] as $blogId => $settings ) {
				$tLocale = new TargetLocale();
				$tLocale->setBlogId( $blogId );
				$tLocale->setLabel( $this->getEntityHelper()->getSiteHelper()->getBlogLabelById( $this->getEntityHelper()->getConnector(),
					$blogId ) );
				$tLocale->setEnabled( array_key_exists( 'enabled', $settings ) && 'on' === $settings['enabled'] );
				$tLocale->setSmartlingLocale( array_key_exists( 'target', $settings ) ? $settings['target'] : - 1 );

				$locales[] = $tLocale;
			}

			$profile->setTargetLocales( $locales );
		}

		$settingsManager->storeEntity( $profile );

		wp_redirect( '/wp-admin/admin.php?page=smartling_configuration_profile_list' );
	}
}