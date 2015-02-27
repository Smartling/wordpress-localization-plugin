<?php

namespace Smartling\Settings;

/**
 * Class TargetLocale
 *
 * @package Smartling\Settings
 */
class TargetLocale {

	/**
	 * @var string
	 */
	private $locale;

	/**
	 * @var string
	 */
	private $target;

	/**
	 * @var int
	 */
	private $blog;

	/**
	 * @var boolean
	 */
	private $enabled;

	/**
	 * @param $locale
	 * @param $target
	 * @param $enabled
	 * @param $blog
	 */
	public function __construct ( $locale, $target, $enabled, $blog ) {
		$this->locale  = $locale;
		$this->target  = $target;
		$this->enabled = $enabled;
		$this->blog    = $blog;
	}

	/**
	 * @return string
	 */
	public function getLocale () {
		return $this->locale;
	}

	/**
	 * @param string $locale
	 */
	public function setLocale ( $locale ) {
		$this->locale = $locale;
	}

	/**
	 * @return string
	 */
	public function getTarget () {
		return $this->target;
	}

	/**
	 * @param string $target
	 */
	public function setTarget ( $target ) {
		$this->target = $target;
	}

	/**
	 * @return boolean
	 */
	public function getEnabled () {
		return $this->enabled;
	}

	/**
	 * @param boolean $enabled
	 */
	public function setEnabled ( $enabled ) {
		$this->enabled = $enabled;
	}

	/**
	 * @return int
	 */
	public function getBlog () {
		return (int) $this->blog;
	}

	/**
	 * @param int $blog
	 */
	public function setBlog ( $blog ) {
		$this->blog = $blog;
	}

	/**
	 * @return array
	 */
	public function toArray () {
		return array (
			'locale'  => $this->getLocale(),
			'target'  => $this->getTarget(),
			'enabled' => $this->getEnabled(),
			'blog'    => $this->getBlog()
		);
	}
}