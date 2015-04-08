<?php
namespace Smartling\Settings;

/**
 * Class Locale
 *
 * @package Smartling\Settings
 */
class Locale {
	/**
	 * @var int
	 */
	private $blogId;

	/**
	 * @var string
	 */
	private $label;

	/**
	 * @return int
	 */
	public function getBlogId () {
		return $this->blogId;
	}

	/**
	 * @param int $blogId
	 */
	public function setBlogId ( $blogId ) {
		$this->blogId = (int) $blogId;
	}

	/**
	 * @return string
	 */
	public function getLabel () {
		return $this->label;
	}

	/**
	 * @param string $label
	 */
	public function setLabel ( $label ) {
		$this->label = $label;
	}
}