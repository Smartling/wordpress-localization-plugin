<?php

namespace Smartling\Base;

use Smartling\Bootstrap;
use Smartling\Helpers\SiteHelper;
use Smartling\MonologWrapper\MonologWrapper;


/**
 * Class LoggerTrait
 *
 * @package Smartling\Base
 */
trait LoggerTrait
{

    private function getSiteContext()
    {
        /**
         * @var SiteHelper $sh
         */
        $sh = Bootstrap::getContainer()
                       ->get('site.helper');

        return $sh->getCurrentBlogId();

    }

    /**
     * @param string $message
     */
    public function logMessage($message)
    {
        MonologWrapper::getLogger(get_class($this))
            ->debug(vsprintf('Site: %s; Message: %s', [$this->getSiteContext(), $message]));
    }
}