<?php

namespace Smartling\Helpers;

/**
 * Runtime non serializing cache
 * Class RuntimeCacheHelper
 * @package Smartling\Helpers
 */
class RuntimeCacheHelper
{
    const DEFAULT_SCOPE = 'default';

    /**
     * @var RuntimeCacheHelper
     */
    private static $instance = null;

    /**
     * @var array
     */
    private $storage = [];

    /**
     * @return RuntimeCacheHelper
     */
    public static function getInstance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function checkScope($scope)
    {
        return array_key_exists($scope, $this->storage);
    }

    private function checkElement($key, $scope)
    {
        return $this->checkScope($scope) && array_key_exists($key, $this->storage[$scope]);
    }

    /**
     * @param string $key
     * @param string $scope
     *
     * @return mixed
     */
    public function get($key, $scope = self::DEFAULT_SCOPE)
    {
        return $this->checkElement($key, $scope) ? $this->storage[$scope][$key] : false;
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param string $scope
     */
    public function set($key, $value, $scope = self::DEFAULT_SCOPE)
    {
        $this->storage[$scope][$key] = $value;
    }
}