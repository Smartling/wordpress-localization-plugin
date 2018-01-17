<?php

namespace Smartling\MonologWrapper\Logger;

/**
 * Class DevNullLogger
 *
 * @package LogConfigExample\Logger
 */
class DevNullLogger extends LevelLogger {

  /**
   * {@inheritdoc}
   */
  public function addRecord($level, $message, array $context = []) {
    return true;
  }

}
