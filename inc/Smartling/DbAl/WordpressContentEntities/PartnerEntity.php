<?php

namespace Smartling\DbAl\WordpressContentEntities;


use Psr\Log\LoggerInterface;
use Smartling\Helpers\WordpressContentTypeHelper;

/**
 * Class PartnerEntity
 *
 * @package Smartling\DbAl\WordpressContentEntities
 */
class PartnerEntity extends PostEntityStd
{
    /**
     * @inheritdoc
     */
    public function __construct(LoggerInterface $logger)
    {
        parent::__construct($logger,WordpressContentTypeHelper::CONTENT_TYPE_POST_PARTNER,[]);
        //$this->setType(WordpressContentTypeHelper::CONTENT_TYPE_POST_PARTNER);
    }
}