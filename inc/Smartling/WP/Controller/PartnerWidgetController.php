<?php

namespace Smartling\WP\Controller;

use Smartling\Helpers\WordpressContentTypeHelper;

/**
 * Class PartnerWidgetController
 *
 * @package Smartling\WP\Controller
 */
class PartnerWidgetController extends PostWidgetController {

	/**
	 * @var string
	 */
	protected $servedContentType = WordpressContentTypeHelper::CONTENT_TYPE_POST_PARTNER;

	/**
	 * @var string
	 */
	protected $needSave = 'Need to save the partner';

	/**
	 * @var string
	 */
	protected $noOriginalFound = 'No original partner found';
}