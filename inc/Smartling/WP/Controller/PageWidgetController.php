<?php

namespace Smartling\WP\Controller;

use Smartling\Helpers\WordpressContentTypeHelper;

/**
 * Class PageWidgetController
 *
 * @package Smartling\WP\Controller
 */
class PageWidgetController extends PostWidgetController {

	/**
	 * @var string
	 */
	protected $servedContentType = WordpressContentTypeHelper::CONTENT_TYPE_PAGE;

	/**
	 * @var string
	 */
	protected $needSave = 'Need to save the page';

	/**
	 * @var string
	 */
	protected $noOriginalFound = 'No original page found';

	/**
	 * @inheritdoc
	 */
	protected function isAllowedToSave ( $post_id ) {
		return current_user_can( 'edit_page', $post_id );
	}
}