<?php

namespace Smartling\WP;

class Settings extends WPAbstract implements WPHookInterface {

	public function wp_enqueue () {
		wp_enqueue_style(
			$this->getPluginInfo()->getName(),
			$this->getPluginInfo()->getUrl() . '/css/smartling-connector-admin.css',
			array (),
			$this->getPluginInfo()->getVersion(),
			'all'
		);

		wp_enqueue_script(
			$this->getPluginInfo()->getName(),
			$this->getPluginInfo()->getUrl() . '/js/smartling-connector-admin.js',
			array ( 'jquery' ),
			$this->getPluginInfo()->getVersion(),
			false
		);
	}

	public function register () {
		add_action( 'wp_enqueue_scripts', array ( $this, 'wp_enqueue' ) );
		add_action( 'admin_menu', array ( $this, 'menu' ) );
		add_action( 'network_admin_menu', array ( $this, 'menu' ) );
		add_action( 'admin_post_smartling_settings', array ( $this, 'save' ) );
	}

	public function menu () {
		add_submenu_page(
			'smartling-submissions',
			'Settings',
			'Settings',
			'Administrator',
			'smartling-settings',
			array (
				$this,
				'view'
			)
		);
	}

	public function getSiteLocales () {
		return $this->getMultiLingualConnector()->getLocales();
	}

	public function save () {
		$settings = $_REQUEST['smartling_settings'];

		$options       = $this->getPluginInfo()->getOptions();
		$accountInfo   = $options->getAccountInfo();
		$targetLocales = $options->getLocales();

		if ( array_key_exists( "apiUrl", $settings ) ) {
			$accountInfo->setApiUrl( $settings["apiUrl"] );
		}

		if ( array_key_exists( "projectId", $settings ) ) {
			$accountInfo->setProjectId( $settings["projectId"] );
		}

		if ( array_key_exists( "apiKey", $settings ) ) {
			$accountInfo->setKey( $settings["apiKey"] );
		}

		if ( array_key_exists( "retrievalType", $settings ) ) {
			$accountInfo->setRetrievalType( $settings["retrievalType"] );
		}

		if ( array_key_exists( "callbackUrl", $settings ) ) {
			$accountInfo->setCallBackUrl( $settings["callbackUrl"] == "on" ? true : false );

		}

		if ( array_key_exists( "autoAuthorize", $settings ) ) {
			$accountInfo->setAutoAuthorize( $settings["autoAuthorize"] == "on" ? true : false );
		}

		if ( array_key_exists( "defaultLocale", $settings ) ) {
			$targetLocales->setDefaultLocale( $settings["defaultLocale"] );
		}

		if ( array_key_exists( "targetLocales", $settings ) ) {
			$locales = array ();
			foreach ( $settings["targetLocales"] as $key => $locale ) {

				$locales[] = array (
					"locale"  => $key,
					"target"  => $locale["target"],
					"enabled" => array_key_exists( "enabled", $locale ) && $locale["enabled"] == "on" ? true : false
				);
			}

			$existLocales = array ();
			foreach ( $targetLocales->getTargetLocales( true ) as $targetLocale ) {
				$exist = false;
				foreach ( $locales as $locale ) {
					if ( $locale["locale"] == $targetLocale->getLocale() ) {
						$exist = true;
						break;
					}
				}
				if ( ! $exist ) {
					$existLocales[] = $targetLocale->toArray();
				}
			}
			$locales = array_merge( $locales, $existLocales );
			$targetLocales->setTargetLocales( $locales );
		}

		$options->save();

		wp_redirect( $_REQUEST["_wp_http_referer"] );
	}
}