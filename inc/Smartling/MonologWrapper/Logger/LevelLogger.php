<?php

namespace Smartling\MonologWrapper\Logger;

use Monolog\Logger;
use Psr\Log\LogLevel;

/**
 * Class LevelLogger
 *
 * @package LogConfigExample\Logger
 */
class LevelLogger extends Logger {

  /**
   * Do not record messages lower than this level.
   *
   * @var int level
   */
  private $level = Logger::DEBUG;

  /**
   * @var array
   */
  private $levelMapping = [
    LogLevel::EMERGENCY => Logger::EMERGENCY,
    LogLevel::ALERT => Logger::ALERT,
    LogLevel::CRITICAL => Logger::CRITICAL,
    LogLevel::ERROR => Logger::ERROR,
    LogLevel::WARNING => Logger::WARNING,
    LogLevel::NOTICE => Logger::NOTICE,
    LogLevel::INFO => Logger::INFO,
    LogLevel::DEBUG => Logger::DEBUG,
  ];

  /**
   * LevelLogger constructor.
   *
   * @param string $name
   * @param string $level
   * @param array $handlers
   * @param array $processors
   */
  public function __construct($name, $level = LogLevel::DEBUG, $handlers = [], $processors = []) {
    parent::__construct($name, $handlers, $processors);

    if (isset($this->levelMapping[strtolower($level)])) {
      $this->level = $this->levelMapping[strtolower($level)];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function addRecord($level, $message, array $context = []) {
    if ($level >= $this->level) {
      return parent::addRecord($level, $message, $context);
    }

    return false;
  }

}
