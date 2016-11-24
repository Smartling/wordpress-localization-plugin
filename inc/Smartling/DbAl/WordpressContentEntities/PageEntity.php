<?php

namespace Smartling\DbAl\WordpressContentEntities;

use Psr\Log\LoggerInterface;
use Smartling\Helpers\WordpressContentTypeHelper;

/**
 * Class PageEntity
 * @property string page_template
 * @package Smartling\DbAl\WordpressContentEntities
 */
class PageEntity extends PostEntityStd
{

    /**
     * @inheritdoc
     */
    public function __construct(LoggerInterface $logger)
    {
        parent::__construct($logger,'page',[]);


        $ownFields = [
            'page_template',
        ];
        $this->fields = array_merge($this->fields, $ownFields);
        $this->hashAffectingFields = array_merge([], $ownFields);

    }

}