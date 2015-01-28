<?php
/**
 * Created by PhpStorm.
 * User: sergey@slepokurov.com
 * Date: 20.01.2015
 * Time: 12:40
 */

namespace Smartling\Helpers;


class PluginInfo {
	public function __construct ( $name, $version, $url, $dir, $domain, $options ) {
		$this->name    = $name;
		$this->version = $version;
		$this->url     = $url;
		$this->dir     = $dir;
		$this->domain  = $domain;
		$this->options = $options;
	}

	/**
	 * @var Options
	 */
	private $options;

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
	 * @return string
	 */
	public function getDomain () {
		return $this->domain;
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
	 * @return Options
	 */
	public function getOptions () {
		return $this->options;
	}
}