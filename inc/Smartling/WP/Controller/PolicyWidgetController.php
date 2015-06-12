<?php

namespace Smartling\WP\Controller;

use Smartling\Helpers\WordpressContentTypeHelper;

/**
 * Class PolicyWidgetController
 *
 * @package Smartling\WP\Controller
 */
class PolicyWidgetController extends PostWidgetController {

	/**
	 * @var string
	 */
	protected $servedContentType = WordpressContentTypeHelper::CONTENT_TYPE_POST_POLICY;

	/**
	 * @var string
	 */
	protected $noOriginalFound = 'No original policy found';
}