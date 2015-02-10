<?php

namespace Smartling\Helpers;

use Smartling\Settings\SettingsManager;

/**
 * Class PluginInfo
 *
 * @package Smartling\Helpers
 */
class PluginInfo {

	/**
	 * @param string          $name
	 * @param string          $version
	 * @param string          $url
	 * @param string          $dir
	 * @param string          $domain
	 * @param SettingsManager $settingsManager
	 * @param string          $upload
	 */
	public function __construct ( $name, $version, $url, $dir, $domain, $settingsManager, $upload ) {
		$this->name            = $name;
		$this->version         = $version;
		$this->url             = $url;
		$this->dir             = $dir;
		$this->domain          = $domain;
		$this->settingsManager = $settingsManager;
		$this->upload          = $upload;
	}

	/**
	 * @var SettingsManager
	 */
	private $settingsManager;

	/**
	 * @var string
	 */
	private $name;
	/**
	 * @var string
	 */
	private $version;
	/**
	 * @var string
	 */
	private $domain;
	/**
	 * @var string
	 */
	private $url;
	/**
	 * @var string
	 */
	private $dir;

	/**
	 * @var string
	 */
	private $upload;

	/**
	 * @return SettingsManager
	 */
	public function getSettingsManager () {
		return $this->settingsManager;
	}

	/**
	 * @return string
	 */
	public function getName () {
		return $this->name;
	}

	/**
	 * @return string
	 */
	public function getVersion () {
		return $this->version;
	}

	/**
	 * @return string
	 */
	public function getDomain () {
		return $this->domain;
	}

	/**
	 * @return string
	 */
	public function getUrl () {
		return $this->url;
	}

	/**
	 * @return string
	 */
	public function getDir () {
		return $this->dir;
	}

	/**
	 * @return string
	 */
	public function getUpload () {
		return $this->upload;
	}
}