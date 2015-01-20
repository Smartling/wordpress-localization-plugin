<?php
namespace Smartling\DbAl;

use Smartling\Exception\SmartlingDirectRunRuntimeException;

abstract class MultilangPluginAbstract implements MultilangPluginProxy
{

    /**
     * Fallback for direct run if Wordpress functionality is not reachable
     * @throws SmartlingDirectRunRuntimeException
     */
    protected function fallbackErrorMessage($message)
    {
        $this->fallbackErrorMessage($message);

        throw new SmartlingDirectRunRuntimeException($message);
    }

}