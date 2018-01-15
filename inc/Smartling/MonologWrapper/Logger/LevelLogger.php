<?php

namespace Smartling\MonologWrapper\Logger;

use InvalidArgumentException;
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
   * LevelLogger constructor.
   *
   * @param string $name
   * @param string $level
   * @param array $handlers
   * @param array $processors
   */
  public function __construct($name, $level = LogLevel::DEBUG, $handlers = [], $processors = []) {
    parent::__construct($name, $handlers, $processors);

    $level = strtoupper($level);

    if (!in_array($level, self::$levels, TRUE)) {
      throw new InvalidArgumentException('Level "' . $level . '" is not defined, use one of: ' . implode(', ', array_values(self::$levels)));
    }

    $this->level = array_search($level, self::$levels);
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
