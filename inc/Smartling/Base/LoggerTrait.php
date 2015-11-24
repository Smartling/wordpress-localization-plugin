<?php

namespace Smartling\Base;

use Smartling\Bootstrap;
use Smartling\Helpers\SiteHelper;


/**
 * Class LoggerTrait
 *
 * @package Smartling\Base
 */
trait LoggerTrait {

	/**
	 * @var string
	 * property from settings.yml that can be true of false
	 */
	private $loggingSettingsKey = '';

	/**
	 * @return string
	 */
	public function getLoggingSettingsKey () {
		return $this->loggingSettingsKey;
	}

	/**
	 * @param string $loggingSettingsKey
	 */
	public function setLoggingSettingsKey ( $loggingSettingsKey ) {
		$this->loggingSettingsKey = $loggingSettingsKey;
	}

	/**
	 * @var bool
	 */
	private $loggingKeyState = null;

	/**
	 * @param bool|false $default
	 */
	private function readSettingState ( $default = false ) {
		try {
			$this->loggingKeyState = (bool) Bootstrap::getContainer()->getParameter( $this->getLoggingSettingsKey() );
		} catch ( \Exception $e ) {
			$this->loggingKeyState = $default;
		}
	}

	private function getSiteContext () {
		/**
		 * @var SiteHelper $sh
		 */
		$sh = Bootstrap::getContainer()->get( 'site.helper' );

		return $sh->getCurrentBlogId();

	}

	/**
	 * @param string $message
	 */
	public function logMessage ( $message ) {
		if ( is_null( $this->loggingKeyState ) ) {
			$this->loggingKeyState = $this->readSettingState();
		}

		Bootstrap::getLogger()->debug( vsprintf( 'Site: %s; Message: %s', [ $this->getSiteContext(), $message ] ) );
	}
}