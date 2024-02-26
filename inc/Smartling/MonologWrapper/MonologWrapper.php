<?php

namespace Smartling\MonologWrapper;

use Smartling\Models\LoggerWithStringContext;
use Smartling\MonologWrapper\Logger\DevNullLogger;
use Smartling\MonologWrapper\Logger\LevelLogger;
use Smartling\Vendor\Symfony\Component\DependencyInjection\ContainerBuilder;

class MonologWrapper
{
    private static array $loggers = [];

    /**
     * Remove all loggers from registry.
     */
    public static function clear(): void
    {
        static::$loggers = [];
    }

    public static function init(ContainerBuilder $container): void
    {
        foreach ($container->getDefinitions() as $serviceId => $serviceDefinition) {
            if (in_array($serviceDefinition->getClass(),
                         [
                             DevNullLogger::class,
                             LevelLogger::class,
                         ],
                true)) {
                $service = $container->get($serviceId);
                static::$loggers[$service->getName()] = $service;
            }
        }

        // Sort loggers: longest names come first.
        uasort(static::$loggers, static function ($a, $b) {
            return strlen($a->getName()) > strlen($b->getName()) ? -1 : 1;
        });
    }

    /**
     * Returns needed logger object.
     * 1. If $name is passed then it looks for logger with name equals/partially
     *    equals to passed name (class/namespace logger). If there is no
     *    appropriate logger then it looks for 'default' logger.
     * 2. If $name isn't passed then it looks for 'default' logger.
     * 3. Fallback to DevNullLogger in order to not break anything.
     */
    public static function getLogger(?string $name = null): LoggerWithStringContext
    {
        if (!empty($name)) {
            // Look for full match (logger for class). Class logger has more priority
            // than namespace logger.
            foreach (static::$loggers as $loggerName => $logger) {
                if ($name === $loggerName) {
                    return $logger;
                }
            }

            // Look for partial match (logger for namespace).
            foreach (static::$loggers as $loggerName => $logger) {
                if (str_contains($name, $loggerName)) {
                    return $logger;
                }
            }

            if (isset(static::$loggers['default'])) {
                // Return logger with channel == class name.
                return static::$loggers['default']->withName($name);
            }
        } elseif (isset(static::$loggers['default'])) {
            return static::$loggers['default'];
        }

        return new DevNullLogger('NullLogger');
    }
}
