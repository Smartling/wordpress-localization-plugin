<?php

namespace Smartling\Helpers;

use Psr\Log\LoggerInterface;
use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\Exception\SmartlingDirectRunRuntimeException;

/**
 * Class SiteHelper
 *
 * Helps to manipulate with Sites and Blogs
 *
 * @package Smartling\Helpers
 */
class SiteHelper {

	/**
	 * @var LoggerInterface
	 */
	private $_logger;

	/**
	 * @param LoggerInterface $logger
	 */
	public function __construct ( LoggerInterface $logger ) {
		$this->_logger = $logger;
	}

	/**
	 * @var array
	 */
	protected static $_siteCache = array ();

	/**
	 * @var array
	 */
	protected static $_flatBlogIdCache = array ();

	/**
	 * Fallback for direct run if Wordpress functionality is not reachable
	 *
	 * @throws SmartlingDirectRunRuntimeException
	 */
	private function directRunDetectedFallback () {
		$message = 'Direct run detected. Required run as Wordpress plugin.';

		$this->fallbackErrorMessage( $message );

		throw new SmartlingDirectRunRuntimeException( $message );
	}

	private function cacheSites () {
		if ( empty( self::$_siteCache ) ) {
			$sites = wp_get_sites();

			foreach ( $sites as $site ) {
				self::$_siteCache[ $site['site_id'] ][] = $site['blog_id'];
				self::$_flatBlogIdCache[]               = (int) $site['blog_id'];
			}
		}
	}

	/**
	 * @return array
	 * @throws SmartlingDirectRunRuntimeException
	 */
	public function listSites () {
		! function_exists( 'wp_get_sites' )
		&& $this->directRunDetectedFallback();

		$this->cacheSites();

		return array_keys( self::$_siteCache );
	}

	/**
	 * @param int $siteId
	 *
	 * @return mixed
	 * @throws SmartlingDirectRunRuntimeException
	 * @throws InvalidArgumentException
	 */
	public function listBlogs ( $siteId = 1 ) {
		! function_exists( 'wp_get_sites' )
		&& $this->directRunDetectedFallback();

		$this->cacheSites();

		if ( isset( self::$_siteCache[ $siteId ] ) ) {
			return self::$_siteCache[ $siteId ];
		} else {
			$message = 'Invalid site_id value set.';
			throw new \InvalidArgumentException( $message );
		}
	}

	/**
	 * @return integer
	 * @throws SmartlingDirectRunRuntimeException
	 */
	public function getCurrentSiteId () {
		if ( function_exists( 'get_current_site' ) ) {
			return get_current_site()->id;
		} else {
			$this->directRunDetectedFallback();
		}
	}

	/**
	 * @return int
	 * @throws SmartlingDirectRunRuntimeException
	 */
	public function getCurrentBlogId () {
		if ( function_exists( 'get_current_blog_id' ) ) {
			return get_current_blog_id();
		} else {
			$this->directRunDetectedFallback();
		}
	}

	/**
	 * @param $blogId
	 *
	 * @throws SmartlingDirectRunRuntimeException
	 */
	public function switchBlogId ( $blogId ) {
		$this->cacheSites();

		if ( ! in_array( $blogId, self::$_flatBlogIdCache ) ) {
			$message = vsprintf( 'Invalid blogId value. Got %s, expected one of [%s]',
				array ( $blogId, implode( ',', self::$_flatBlogIdCache ) ) );

			throw new \InvalidArgumentException( $message );
		}

		if ( function_exists( 'switch_to_blog' ) ) {
			switch_to_blog( $blogId );
		} else {
			$this->directRunDetectedFallback();
		}

	}

	public function restoreBlogId () {
		if ( ! function_exists( 'restore_current_blog' ) || ! function_exists( 'ms_is_switched' ) ) {
			$this->directRunDetectedFallback();
		}

		if ( false === ms_is_switched() ) {
			$message = 'Blog was not switched previously';
			throw new \LogicException( $message );
		}

		restore_current_blog();
	}

	/**
	 * Returns locale of current blog
	 * @param LocalizationPluginProxyInterface $localizationPlugin
	 *
	 * @return string
	 */
	public function getCurrentBlogLocale (LocalizationPluginProxyInterface $localizationPlugin) {
		return $localizationPlugin->getBlogLocaleById($this->getCurrentBlogId());
	}
}