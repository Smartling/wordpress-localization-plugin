<?php

namespace Smartling\DbAl;

use Mlp_Content_Relations;
use Mlp_Content_Relations_Interface;
use Mlp_Site_Relations;
use Mlp_Site_Relations_Interface;
use Psr\Log\LoggerInterface;
use Smartling\Helpers\SiteHelper;
use Smartling\Helpers\WordpressContentTypeHelper;
use Smartling\Submissions\SubmissionEntity;
use wpdb;

/**
 * Class MultiligualPressProConnector
 *
 * @package Smartling\DbAl
 */
class MultiligualPressProConnector extends LocalizationPluginAbstract {

	/**
	 * option key name
	 */
	const MULTILINGUAL_PRESS_PRO_SITE_OPTION = 'inpsyde_multilingual';

	/**
	 * table name
	 */
	const ML_SITE_LINK_TABLE = 'mlp_site_relations';

	/**
	 * table name
	 */
	const ML_CONTENT_LINK_TABLE = 'multilingual_linked';

	/**
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * @return wpdb
	 */
	public function getWpdb () {
		return $this->wpdb;
	}

	/**
	 * @var array
	 */
	protected static $_blogLocalesCache = array ();

	/**
	 * @throws \Exception
	 */
	private function cacheLocales () {
		if ( empty( self::$_blogLocalesCache ) ) {
			$rawValue = get_site_option( self::MULTILINGUAL_PRESS_PRO_SITE_OPTION, false, false );

			if ( false === $rawValue ) {
				throw new \Exception( 'Multilingual press PRO is not installed/configured.' );
			} else {
				foreach ( $rawValue as $blogId => $item ) {
					self::$_blogLocalesCache[ $blogId ] = array (
						'text' => $item['text'],
						'lang' => $item['lang']
					);
				}
			}
		}
	}

	/**
	 * @inheritdoc
	 */
	public function getLocales () {
		if ( ! function_exists( 'get_site_option' ) ) {
			$this->directRunFallback( 'Direct run detected. Required run as Wordpress plugin.' );
		}
		$this->cacheLocales();

		$locales = array ();
		foreach ( self::$_blogLocalesCache as $blogId => $blogLocale ) {
			$locales[ $blogId ] = $blogLocale['text'];
		}

		return $locales;
	}

	/**
	 * @inheritdoc
	 */
	public function getBlogLocaleById ( $blogId ) {
		if ( ! function_exists( 'get_site_option' ) ) {
			$this->directRunFallback( 'Direct run detected. Required run as Wordpress plugin.' );
		}

		$this->cacheLocales();

		$this->helper->switchBlogId( $blogId );

		$locale = self::$_blogLocalesCache[ $this->helper->getCurrentBlogId() ];

		$this->helper->restoreBlogId();

		return $locale['lang'];
	}

	/**
	 * @inheritdoc
	 */
	public function __construct ( LoggerInterface $logger, SiteHelper $helper, array $ml_plugin_statuses ) {
		global $wpdb;

		$this->wpdb = $wpdb;

		parent::__construct( $logger, $helper, $ml_plugin_statuses );

		if ( false === $ml_plugin_statuses['multilingual-press-pro'] ) {
			throw new \Exception( 'Active plugin not found Exception' );
		}
	}

	/**
	 * @return Mlp_Site_Relations_Interface
	 */
	private function initiSiteRelationsSubsystem () {
		return new Mlp_Site_Relations( $this->getWpdb(), self::ML_SITE_LINK_TABLE );
	}

	/**
	 * @return Mlp_Content_Relations_Interface
	 */
	private function initContentRelationSubsystem () {
		return new Mlp_Content_Relations( $this->getWpdb(), $this->initiSiteRelationsSubsystem(),
			$this->getWpdb()->base_prefix . self::ML_CONTENT_LINK_TABLE );
	}

	/**
	 * @inheritdoc
	 */
	function getLinkedBlogIdsByBlogId ( $blogId ) {

		$relations = $this->initiSiteRelationsSubsystem();

		$res = $relations->get_related_sites( $blogId );

		$result = array ();

		foreach ( $res as $site ) {
			$result[] = (int) $site;
		}

		return $result;
	}

	/**
	 * @inheritdoc
	 */
	function getLinkedObjects ( $sourceBlogId, $sourceContentId, $contentType ) {
		$relations = $this->initContentRelationSubsystem();

		return $relations->get_relations( $sourceBlogId, $sourceContentId, $contentType );
	}

	/**
	 * @inheritdoc
	 */
	function linkObjects ( SubmissionEntity $submission ) {
		$relations = $this->initContentRelationSubsystem();

		$contentType = $submission->getContentType();

		$contentType = $contentType === WordpressContentTypeHelper::CONTENT_TYPE_PAGE
			? WordpressContentTypeHelper::CONTENT_TYPE_POST
			: $contentType;

		$contentType = in_array( $contentType,
			WordpressContentTypeHelper::getSupportedTaxonomyTypes() )
			? 'term'
			: $contentType;

		return $relations->set_relation( $submission->sourceBlog, $submission->targetBlog, $submission->sourceGUID,
			$submission->targetGUID, $contentType );
	}

	/**
	 * @inheritdoc
	 */
	function  unlinkObjects ( SubmissionEntity $submission ) {
		$relations = $this->initContentRelationSubsystem();

		return $relations->delete_relation( $submission->sourceBlog, $submission->targetBlog, $submission->sourceGUID,
			$submission->targetGUID, $submission->contentType );
	}
}