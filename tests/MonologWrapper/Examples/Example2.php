<?php

namespace Smartling\Tests\MonologWrapper\Examples;

use Smartling\MonologWrapper\MonologWrapper;

class Example2 {

  public function doSomething() {
    $logger = MonologWrapper::getLogger(get_called_class());

    return [
      'logger' => $logger,
      'records' => [
        'debug' => $logger->debug('Example 2'),
        'info' => $logger->info('Example 2'),
        'notice' => $logger->notice('Example 2'),
        'warning' => $logger->warning('Example 2'),
        'error' => $logger->error('Example 2'),
        'critical' => $logger->critical('Example 2'),
        'alert' => $logger->alert('Example 2'),
        'emergency' => $logger->emergency('Example 2'),
      ]
    ];
  }

}
