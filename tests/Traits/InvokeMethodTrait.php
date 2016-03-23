<?php

namespace Smartling\Tests\Traits;

/**
 * Trait InvokeMethodTrait
 * @package Smartling\Tests\Traits
 */
trait InvokeMethodTrait
{
    /**
     * Invokes protected or private method of given object.
     *
     * @param mixed  $object
     *   Object with protected or private method to invoke.
     * @param string $methodName
     *   Name of the property to invoke.
     * @param array  $parameters
     *   Array of parameters to be passed to invoking method.
     *
     * @return mixed
     *   Value invoked method will return or exception.
     */
    protected function invokeMethod($object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    /**
     * Invokes protected or private static method of given class.
     *
     * @param Object $object
     *   Object with protected or private method to invoke.
     * @param string $methodName
     *   Name of the property to invoke.
     * @param array  $parameters
     *   Array of parameters to be passed to invoking method.
     *
     * @return mixed
     *   Value invoked method will return or exception.
     */
    protected function invokeStaticMethod($className, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass($className);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs(null, $parameters);
    }
}