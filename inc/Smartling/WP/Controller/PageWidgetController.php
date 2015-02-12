<?php

namespace Smartling\WP\Controller;

use Smartling\Helpers\WordpressContentTypeHelper;

/**
 * Class PageWidgetController
 *
 * @package Smartling\WP\Controller
 */
class PageWidgetController extends PostWidgetController {

	protected $servedContentType = WordpressContentTypeHelper::CONTENT_TYPE_PAGE;

	protected $needSave = 'Need to save the page';

	/**
	 * @inheritdoc
	 */
	protected function isAllowedToSave($post_id)
	{
		return current_user_can( 'edit_page', $post_id );
	}
}