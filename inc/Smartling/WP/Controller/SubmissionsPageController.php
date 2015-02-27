<?php

namespace Smartling\WP\Controller;

use Smartling\Base\SmartlingCore;
use Smartling\Bootstrap;
use Smartling\Helpers\EntityHelper;
use Psr\Log\LoggerInterface;
use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\Helpers\WordpressContentTypeHelper;
use Smartling\Helpers\PluginInfo;
use Smartling\Submissions\SubmissionManager;
use Smartling\WP\View\SubmissionTableWidget;
use Smartling\WP\WPAbstract;
use Smartling\WP\WPHookInterface;

/**
 * Class SubmissionsPageController
 *
 * @package Smartling\WP\Controller
 */
class SubmissionsPageController
	extends WPAbstract
	implements WPHookInterface {

	const SUBMISSION_CHECKED_KEY = "smartling-page-checked-items";
	const CACHE_SLIDE_EXPIRATION = "PT1H";
	const CACHE_EXPIRATION = 7200;

	public function wp_enqueue () {
		wp_enqueue_script(
			$this->getPluginInfo()->getName() . "submission",
			$this->getPluginInfo()->getUrl() . 'js/smartling-submissions-page.js',
			array ( 'jquery' ),
			$this->getPluginInfo()->getVersion(),
			false
		);
	}

	/**
	 * @inheritdoc
	 */
	public function register () {
		add_action( 'wp_ajax_ajax_submissions_update_status', array ( $this, 'ajaxHandler' ) );
		add_action( 'wp_ajax_ajax_submissions', array ( $this, 'ajaxHandler' ) );
		add_action( 'admin_enqueue_scripts', array ( $this, 'wp_enqueue' ) );
		add_action( 'admin_menu', array ( $this, 'menu' ) );
		add_action( 'network_admin_menu', array ( $this, 'menu' ) );
	}


	public function ajaxHandler () {
		if ( $_REQUEST["action"] == "ajax_submissions_update_status" ) {

			$items = $this->checkItems( $_REQUEST["ids"] );

			if ( count( $items ) > 0 ) {
				/**
				 * @var SmartlingCore $ep
				 */
				$ep = Bootstrap::getContainer()->get( 'entrypoint' );
				$ep->bulkCheckByIds( $items );
			}
		}

		$wp_list_table = new SubmissionTableWidget( $this->getManager() );
		$wp_list_table->ajax_response();
	}

	/**
	 * @param array $items
	 *
	 * @return array
	 */
	public function checkItems ( array $items ) {
		$result = array ();
		$cache  = $this->getCache();

		$cachedItems = $cache->get( self::SUBMISSION_CHECKED_KEY );

		$now   = new \DateTime( "now" );
		$slide = new \DateTime( "now" );
		$slide = $slide->add( new \DateInterval( self::CACHE_SLIDE_EXPIRATION ) );
		foreach ( $items as $item ) {
			$isCached = false;
			if ( $cachedItems ) {
				foreach ( $cachedItems as &$cachedItem ) {
					if ( $cachedItem["item"] == $item ) {
						$isCached = true;
						if ( $cachedItem["expiration"] <= $now ) {
							$result[]                 = $item;
							$cachedItem["expiration"] = $slide;
						}
						break;
					}
				}
			}

			if ( ! $isCached ) {
				$cachedItems[] = array (
					"item"       => $item,
					"expiration" => $slide
				);
				$result[]      = $item;
			}
		}

		$cache->set( self::SUBMISSION_CHECKED_KEY, $cachedItems, self::CACHE_EXPIRATION );

		return $result;
	}


	public function menu () {
		add_menu_page( 'Submissions Board', 'Smartling Connector', 'Administrator', 'smartling-submissions-page',
			array ( $this, 'renderPage' ) );
	}


	public function renderPage () {
		$table = new SubmissionTableWidget( $this->getManager() );

		$this->view( $table );
	}
}