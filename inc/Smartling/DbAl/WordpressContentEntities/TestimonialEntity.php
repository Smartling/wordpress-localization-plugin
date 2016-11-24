<?php

namespace Smartling\DbAl\WordpressContentEntities;

use Psr\Log\LoggerInterface;
use Smartling\Helpers\WordpressContentTypeHelper;

/**
 * Class TestimonialEntity
 *
 * @package Smartling\DbAl\WordpressContentEntities
 */
class TestimonialEntity extends PostEntityStd
{

    /**
     * @inheritdoc
     */
    public function __construct(LoggerInterface $logger)
    {
        parent::__construct($logger,WordpressContentTypeHelper::CONTENT_TYPE_POST_TESTIMONIAL,[]);
        //$this->setType(WordpressContentTypeHelper::CONTENT_TYPE_POST_TESTIMONIAL);
    }
}