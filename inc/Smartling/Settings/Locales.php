<?php

namespace Smartling\Settings;

/**
 * Class Locales
 *
 * @package Smartling\Settings
 */
class Locales {

	/**
	 * @var string
	 */
	private $defaultLocale;

	/**
	 * @var int
	 */
	private $defaultBlog;

	/**
	 * @var TargetLocale[]
	 */
	private $targetLocales;

	/**
	 * Constructor
	 */
	function __construct () {
		$this->targetLocales = array ();
	}

	/**
	 * @param string $key
	 *
	 * @return mixed
	 */
	public function get ( $key ) {
		$values = get_site_option( $key );
		if ( $values ) {
			$this->setDefaultLocale( $values['defaultLocale'] );
			$this->setTargetLocales( $values['targetLocales'] );
			$this->setDefaultBlog( $values['defaultBlog'] );
		}

		return $values;
	}

	/**
	 * @param string $key
	 */
	public function save ( $key ) {
		$option = get_site_option( $key );
		$values = $this->toArray();

		if ( ! $option ) {
			add_site_option( $key, $values );
		} else {
			update_site_option( $key, $values );
		}
	}

	/**
	 * @return array
	 */
	public function toArray () {
		$targetLocales = array ();
		foreach ( $this->getTargetLocales( true ) as $targetLocale ) {
			$targetLocales[] = $targetLocale->toArray();
		}

		return array (
			'defaultLocale' => trim( $this->getDefaultLocale() ),
			'defaultBlog'   => $this->getDefaultBlog(),
			'targetLocales' => $targetLocales
		);
	}

	/**
	 * @param bool $addDefault
	 *
	 * @return TargetLocale[]
	 */
	public function getTargetLocales ( $addDefault = false ) {
		$locales = array ();
		foreach ( $this->targetLocales as $target ) {
			if ( $addDefault || $target->getLocale() !== $this->getDefaultLocale() ) {
				$locales[] = $target;
			}
		}

		return $locales;
	}

	/**
	 * @param array $targetLocales
	 */
	public function setTargetLocales ( $targetLocales ) {
		$this->targetLocales = $this->parseTargetLocales( $targetLocales );
	}

	/**
	 * @return string
	 */
	public function getDefaultLocale () {
		return $this->defaultLocale;
	}

	/**
	 * @param string $defaultLocale
	 */
	public function setDefaultLocale ( $defaultLocale ) {
		$this->defaultLocale = $defaultLocale;
	}

	/**
	 * @return int
	 */
	public function getDefaultBlog () {
		return (int) $this->defaultBlog;
	}

	/**
	 * @param int $defaultBlog
	 */
	public function setDefaultBlog ( $defaultBlog ) {
		$this->defaultBlog = $defaultBlog;
	}

	/**
	 * @param $targetLocales
	 *
	 * @return array
	 */
	private function parseTargetLocales ( $targetLocales ) {
		$locales = array ();
		if ( $targetLocales ) {
			foreach ( $targetLocales as $raw ) {
				$locales[] = new TargetLocale( $raw['locale'], $raw['target'], $raw['enabled'], $raw["blog"] );
			}
		}

		return $locales;
	}
}