<?php

namespace Smartling\DbAl\WordpressContentEntities;

use Psr\Log\LoggerInterface;
use Smartling\Helpers\WordpressContentTypeHelper;

/**
 * Class PolicyEntity
 *
 * @package Smartling\DbAl\WordpressContentEntities
 */
class PolicyEntity extends PostEntityStd
{

    /**
     * @inheritdoc
     */
    public function __construct(LoggerInterface $logger)
    {
        parent::__construct($logger,WordpressContentTypeHelper::CONTENT_TYPE_POST_POLICY,[]);
        //$this->setType(WordpressContentTypeHelper::CONTENT_TYPE_POST_POLICY);
    }

}