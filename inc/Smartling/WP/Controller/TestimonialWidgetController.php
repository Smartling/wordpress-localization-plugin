<?php

namespace Smartling\WP\Controller;

use Smartling\Helpers\WordpressContentTypeHelper;

/**
 * Class TestimonialWidgetController
 *
 * @package Smartling\WP\Controller
 */
class TestimonialWidgetController extends PostWidgetController
{

    /**
     * @var string
     */
    protected $servedContentType = WordpressContentTypeHelper::CONTENT_TYPE_POST_TESTIMONIAL;

    /**
     * @var string
     */
    protected $needSave = 'Need to save the testimonial';

    /**
     * @var string
     */
    protected $noOriginalFound = 'No original testimonial found';
}