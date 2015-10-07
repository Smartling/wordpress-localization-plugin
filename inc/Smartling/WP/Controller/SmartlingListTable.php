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

		$specialTypes = [WordpressContentTypeHelper::CONTENT_TYPE_WIDGET,];

		$postTypes = $siteHelper->getPostTypes();
		$termTypes = $siteHelper->getTermTypes();

		$activeTypes = $specialTypes;

		$activeTypes = array_merge($activeTypes, $postTypes, $termTypes );

		$types = [ ];

		foreach ( $activeTypes as $activeType ) {
			if ( array_key_exists( $activeType, $supportedTypes ) ) {
				$types[ $activeType ] = $supportedTypes[ $activeType ];
			}
		}

		return $types;
	}
}