<?php

namespace Smartling\WP\Controller;

use Smartling\Helpers\SiteHelper;
use Smartling\Helpers\WordpressContentTypeHelper;
use WP_List_Table;

/**
 * Class SmartlingListTable
 *
 * @package Smartling\WP\Controller
 */
class SmartlingListTable extends WP_List_Table {

	/**
	 * @param SiteHelper $siteHelper
	 *
	 * @return array
	 */
	protected function getActiveContentTypes ( SiteHelper $siteHelper ) {
		$supportedTypes = WordpressContentTypeHelper::getLabelMap();

		$postTypes = $siteHelper->getPostTypes();
		$termTypes = $siteHelper->getTermTypes();

		$activeTypes = array_merge( $postTypes, $termTypes );

		$types = array ();

		foreach ( $activeTypes as $activeType ) {
			if ( array_key_exists( $activeType, $supportedTypes ) ) {
				$types[ $activeType ] = $supportedTypes[ $activeType ];
			}
		}

		return $types;
	}
}