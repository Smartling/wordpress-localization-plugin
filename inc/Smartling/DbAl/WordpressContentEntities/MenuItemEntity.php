<?php

namespace Smartling\DbAl\WordpressContentEntities;

use Psr\Log\LoggerInterface;

/**
 * Class MenuItemEntity
 *
 * @package Smartling\DbAl\WordpressContentEntities
 */
class MenuItemEntity extends PostEntityStd
{

    public function __construct(LoggerInterface $logger)
    {
        parent::__construct($logger,'nav-menu-item',[]);
    }
}