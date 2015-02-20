<?php

use Smartling\Bootstrap;
use Smartling\Settings\SettingsConfigurationProfile;
use Smartling\Settings\SettingsManager;
use Smartling\Settings\TargetLocale;

class SettingsManagerTest extends PHPUnit_Framework_TestCase {

	public function __construct ( $name = null, array $data = array (), $dataName = '' ) {
		parent::__construct( $name, $data, $dataName );

		$this->init();
	}

	private function init () {
		if ( ! function_exists( 'get_site_option' ) ) {
			function get_site_option ( $key, $default = null, $useCache = true ) {
				switch ( $key ) {
					case SettingsManager::SMARTLING_ACCOUNT_INFO: {
						return array (
							'apiUrl'        => 'https://api.smartling.com/v1',
							'projectId'     => 'a',
							'key'           => 'b',
							'retrievalType' => 'pseudo',
							'callBackUrl'   => '',
							'autoAuthorize' => true
						);
						break;
					}
					case SettingsManager::SMARTLING_LOCALES: {
						return array (
							'defaultLocale' => 'en-US',
							'targetLocales' => array ( array ( 'locale'  => 'ru-Ru',
							                                   'target'  => true,
							                                   'enabled' => true,
							                                   'blog'    => 2
							)
							),
							'defaultBlog'   => 1

						);
						break;
					}
				}

			}
		}
	}

	public function testSettingsLoad () {
		/**
		 * @var SettingsManager $manager
		 */
		$manager = Bootstrap::getContainer()->get( 'manager.settings' );

		$manager->get();

		$this->assertTrue(is_array($manager->getLocales()->getTargetLocales()));
	}

	public function testCheckTargetLocaleValues()
	{
		/**
		 * @var SettingsManager $manager
		 */
		$manager = Bootstrap::getContainer()->get( 'manager.settings' );

		$manager->get();

		$targetLocales = ($manager->getLocales()->getTargetLocales());

		self::assertTrue(1 === count($targetLocales));

		/**
		 * @var TargetLocale $targetLocale
		 */
		$targetLocale = reset($targetLocales);

		self::assertTrue($targetLocale instanceof TargetLocale);

		self::assertTrue(2 === $targetLocale->getBlog());

		self::assertTrue(true === $targetLocale->getEnabled());
	}

	public function testAccountInfo()
	{
		/**
		 * @var SettingsManager $manager
		 */
		$manager = Bootstrap::getContainer()->get( 'manager.settings' );

		$manager->get();

		$ai = $manager->getAccountInfo();

		self::assertTrue($ai instanceof SettingsConfigurationProfile);
	}

	public function testAutoAuthorizeIsTrue()
	{
		/**
		 * @var SettingsManager $manager
		 */
		$manager = Bootstrap::getContainer()->get( 'manager.settings' );

		$manager->get();

		$ai = $manager->getAccountInfo();

		self::assertTrue($ai->getAutoAuthorize());
	}
}