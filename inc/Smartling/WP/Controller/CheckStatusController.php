<?php
/**
 * Created by PhpStorm.
 * User: sergey@slepokurov.com
 * Date: 02.03.2015
 * Time: 10:17
 */

namespace Smartling\WP\Controller;

use Smartling\Base\SmartlingCore;
use Smartling\Bootstrap;
use Smartling\Helpers\DiagnosticsHelper;
use Smartling\Submissions\SubmissionEntity;
use Smartling\WP\WPAbstract;
use Smartling\WP\WPHookInterface;


/**
 * Class CheckStatusController
 *
 * @package Smartling\WP\Controller
 */
class CheckStatusController extends WPAbstract implements WPHookInterface {
	const SUBMISSION_CHECKED_KEY = "smartling-page-checked-items";
	const CACHE_SLIDE_EXPIRATION = "PT1H";
	const CACHE_EXPIRATION = 7200;

	public function wp_enqueue () {
		wp_enqueue_script(
			$this->getPluginInfo()->getName() . "submission",
			$this->getPluginInfo()->getUrl() . 'js/smartling-submissions-check.js',
			[ 'jquery' ],
			$this->getPluginInfo()->getVersion(),
			false
		);
	}

	/**
	 * @inheritdoc
	 */
	public function register () {
		if ( ! DiagnosticsHelper::isBlocked() ) {
			add_action( 'wp_ajax_ajax_submissions_update_status', [ $this, 'ajaxHandler' ] );
			add_action( 'admin_enqueue_scripts', [ $this, 'wp_enqueue' ] );
		}
	}

	/**
	 * @return array|bool
	 */
	public function ajaxHandler () {
		if ( $_REQUEST["action"] === "ajax_submissions_update_status" ) {

			$items = $this->checkItems( $_REQUEST["ids"] );

			if ( count( $items ) > 0 ) {
				/**
				 * @var SmartlingCore $ep
				 */
				$ep      = Bootstrap::getContainer()->get( 'entrypoint' );
				$results = $ep->bulkCheckByIds( $items );

				$response = [ ];
				foreach ( $results as $result ) {
					/** @var SubmissionEntity $result */
					$response[] = [
						"id"         => $result->getId(),
						"status"     => $result->getStatus(),
						"color"      => $result->getStatusColor(),
						"percentage" => $result->getCompletionPercentage(),
					];
				}
				die( json_encode( $response ) );
			}
		}

		return false;
	}

	/**
	 * @param array $items
	 *
	 * @return array
	 */
	public function checkItems ( array $items ) {
		$result = [ ];
		$cache  = $this->getCache();

		$cachedItems = $cache->get( self::SUBMISSION_CHECKED_KEY );

		$now   = new \DateTime( "now" );
		$slide = new \DateTime( "now" );
		$slide = $slide->add( new \DateInterval( self::CACHE_SLIDE_EXPIRATION ) );
		foreach ( $items as $item ) {
			$isCached = false;
			if ( $cachedItems ) {
				foreach ( $cachedItems as &$cachedItem ) {
					if ( $cachedItem["item"] === $item ) {
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
				$cachedItems[] = [
					"item"       => $item,
					"expiration" => $slide,
				];
				$result[]      = $item;
			}
		}

		$cache->set( self::SUBMISSION_CHECKED_KEY, $cachedItems, self::CACHE_EXPIRATION );

		return $result;
	}
}