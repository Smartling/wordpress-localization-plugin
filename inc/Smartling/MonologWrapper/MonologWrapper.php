<?php

namespace Smartling\MonologWrapper;

use Monolog\Logger;
use Smartling\MonologWrapper\Logger\DevNullLogger;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Class MonologWrapper
 *
 * @package LogConfigExample
 */
class MonologWrapper {

  /**
   * @var Logger[]
   */
  private static $loggers = [];

  /**
   * Remove all loggers from registry.
   */
  public static function clear() {
    self::$loggers = [];
  }

  /**
   * Init wrapper.
   *
   * Fills $loggers array with defined logger services (logger name -> object).
   *
   * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
   * @throws \Throwable
   */
  public static function init(ContainerBuilder $container) {
    foreach ($container->getDefinitions() as $serviceId => $serviceDefinition) {
      $service = $container->get($serviceId);

      if ($service instanceof Logger) {
        self::$loggers[$service->getName()] = $service;
      }
    };

    // Sort loggers: more longest names comes first.
    uasort(self::$loggers, function($a, $b) {
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
   *
   * @param null $name
   * @return DevNullLogger|\Monolog\Logger
   */
  public static function getLogger($name = null) {
    if (!empty($name)) {
      // Look for full match (logger for class). Class logger has more priority
      // than namespace logger.
      foreach (self::$loggers as $loggerName => $logger) {
        if ($name == $loggerName) {
          return $logger;
        }
      }

      // Look for partial match (logger for namespace).
      foreach (self::$loggers as $loggerName => $logger) {
        if (strpos($name, $loggerName) !== false) {
          return $logger;
        }
      }

      if (isset(self::$loggers['default'])) {
        return self::$loggers['default'];
      }
    }
    elseif (isset(self::$loggers['default'])) {
      return self::$loggers['default'];
    }

    return new DevNullLogger('NullLogger');
  }

}
