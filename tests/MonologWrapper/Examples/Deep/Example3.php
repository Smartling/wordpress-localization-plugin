<?php

namespace Smartling\Tests\MonologWrapper\Examples\Deep;

use Smartling\MonologWrapper\MonologWrapper;

class Example3 {

  public function doSomething() {
    $logger = MonologWrapper::getLogger(get_called_class());

    return [
      'logger' => $logger,
      'records' => [
        'debug' => $logger->debug('Example 3'),
        'info' => $logger->info('Example 3'),
        'notice' => $logger->notice('Example 3'),
        'warning' => $logger->warning('Example 3'),
        'error' => $logger->error('Example 3'),
        'critical' => $logger->critical('Example 3'),
        'alert' => $logger->alert('Example 3'),
        'emergency' => $logger->emergency('Example 3'),
      ]
    ];
  }

}
