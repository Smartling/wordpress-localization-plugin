<?php

namespace Smartling\MonologWrapper\Logger;

use Monolog\Logger;

/**
 * Class DevNullLogger
 *
 * @package LogConfigExample\Logger
 */
class DevNullLogger extends Logger {

  /**
   * {@inheritdoc}
   */
  public function addRecord($level, $message, array $context = []) {
    return true;
  }

}
