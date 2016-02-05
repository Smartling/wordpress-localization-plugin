<?php

namespace Smartling;

use Smartling\Settings\ConfigurationProfileEntity;

/**
 * Class ApiWrapperMock
 *
 * @package Smartling
 */
class ApiWrapperMock extends ApiWrapper
{

    /**
     * @inheritdoc
     */
    public function setApi(ConfigurationProfileEntity $profile)
    {
        $this->api = new SmartlingApiMock();
    }
}