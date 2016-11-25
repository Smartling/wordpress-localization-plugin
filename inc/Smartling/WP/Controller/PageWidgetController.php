<?php

namespace Smartling\WP\Controller;

use Smartling\ContentTypes\ContentTypePage;

/**
 * Class PageWidgetController
 * @package Smartling\WP\Controller
 */
class PageWidgetController extends PostWidgetController
{

    /**
     * @var string
     */
    protected $servedContentType = ContentTypePage::WP_CONTENT_TYPE;

    /**
     * @var string
     */
    protected $noOriginalFound = 'No original page found';

    /**
     * @inheritdoc
     */
    protected function isAllowedToSave($post_id)
    {
        return current_user_can('edit_' . $this->servedContentType, $post_id);
    }
}