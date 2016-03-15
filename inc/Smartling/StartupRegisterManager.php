<?php

namespace Smartling;

use Smartling\WP\WPHookInterface;

class StartupRegisterManager
{
    /**
     * @var WPHookInterface[]
     */
    private $services = [];

    /**
     * Registers services
     */
    public function registerServices()
    {
        foreach ($this->services as $service)
        {
            $service->register();
        }
    }

    /**
     * Adds service to collection
     * @param WPHookInterface $service
     */
    public function addService(WPHookInterface $service)
    {
        $this->services[]=$service;
    }
}