<?php

namespace Smartling\Settings;

use InvalidArgumentException;
use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\Helpers\SiteHelper;

/**
 * Class Locales
 *
 * @package Smartling\Settings
 */
class Locales {

	/**
	 * @var SiteHelper
	 */
	private $siteHelper;

	/**
	 * @var LocalizationPluginProxyInterface
	 */
	private $localizationPluginProxy;

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
	 *
	 * @param SiteHelper                       $siteHelper
	 * @param LocalizationPluginProxyInterface $localizationPluginProxy
	 */
	function __construct ( SiteHelper $siteHelper, $localizationPluginProxy ) {
		$this->siteHelper              = $siteHelper;
		$this->localizationPluginProxy = $localizationPluginProxy;
		$this->targetLocales           = array ();
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

		$this->rebuildTargetLocalesList();

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
			'defaultLocale' => $this->getTargetLocaleLabel( $this->getDefaultBlog() ),
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
			if ( $addDefault || ( (int) $target->getBlog() ) !== $this->getDefaultBlog() ) {
				$locales[ $target->getBlog() ] = $target;
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
				if ( $raw instanceof TargetLocale ) {
					$raw = $raw->toArray();
				}
				$locale = TargetLocale::fromArray( $raw );
				$locale->setLocale( $this->getTargetLocaleLabel( $locale->getBlog() ) );
				$locales[ $locale->getBlog() ] = $locale;
			}
		}

		return $locales;
	}

	/**
	 * Generates label like MultilingualPress plugin
	 *
	 * @param int $blogId
	 *
	 * @return string
	 */
	public function getTargetLocaleLabel ( $blogId ) {
		$label = 'Unknown';

		try {
			$this->siteHelper->switchBlogId( $blogId );
			$blog_name = get_bloginfo( 'Name' );
			$this->siteHelper->restoreBlogId();

			$label = vsprintf(
				'%s - %s',
				array (
					$blog_name,
					$this->localizationPluginProxy->getBlogLanguageById( $blogId )
				)
			);
		} catch ( InvalidArgumentException $e ) {
			// unexistant $blogId
		}

		return $label;
	}

	/**
	 * Rebuilds the list of target locales
	 */
	public function rebuildTargetLocalesList () {
		$currentLocales = $this->getTargetLocales( true );
		$blogs          = $this->siteHelper->listBlogs();
		foreach ( $blogs as $blog ) {
			$blog = (int) $blog;
			if ( ! array_key_exists( $blog, $currentLocales ) ) {
				// create structures for new ones
				$currentLocales[ $blog ] =
					new TargetLocale(
						$this->getTargetLocaleLabel( $blog ),
						'',
						false,
						$blog );
			} else {
				// re-generate label (if renamed)
				$currentLocales[ $blog ]->setLocale( $this->getTargetLocaleLabel( $blog ) );
			}
		}
		$this->setTargetLocales( $currentLocales );
	}
}