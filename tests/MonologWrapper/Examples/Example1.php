<?php

namespace Smartling\Tests\MonologWrapper\Examples;

use Smartling\MonologWrapper\MonologWrapper;

class Example1 {

  public function doSomething() {
    $logger = MonologWrapper::getLogger(get_called_class());

    return [
      'logger' => $logger,
      'records' => [
        'debug' => $logger->debug('Example 1'),
        'info' => $logger->info('Example 1'),
        'notice' => $logger->notice('Example 1'),
        'warning' => $logger->warning('Example 1'),
        'error' => $logger->error('Example 1'),
        'critical' => $logger->critical('Example 1'),
        'alert' => $logger->alert('Example 1'),
        'emergency' => $logger->emergency('Example 1'),
      ]
    ];
  }

}
