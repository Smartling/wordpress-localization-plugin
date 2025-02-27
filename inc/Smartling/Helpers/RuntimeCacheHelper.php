<?php

namespace Smartling\Helpers;

/**
 * Runtime non serializing cache
 * Class RuntimeCacheHelper
 * @package Smartling\Helpers
 */
class RuntimeCacheHelper
{
    const string DEFAULT_SCOPE = 'default';

    /**
     * @var RuntimeCacheHelper
     */
    private static $instance = null;

    /**
     * @var array
     */
    private $storage = [];

    public static function getInstance(): RuntimeCacheHelper
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

    public function get(string $key, string $scope = self::DEFAULT_SCOPE): mixed
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
