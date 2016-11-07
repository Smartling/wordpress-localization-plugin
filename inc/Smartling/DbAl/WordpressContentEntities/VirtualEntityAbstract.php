<?php

namespace Smartling\DbAl\WordpressContentEntities;

/**
 * Class VirtualEntityAbstract
 * @package Smartling\DbAl\WordpressContentEntities
 */
abstract class VirtualEntityAbstract extends EntityAbstract
{
    /**
     * @return string
     */
    public function getContentTypeProperty()
    {
        return '';
    }
}