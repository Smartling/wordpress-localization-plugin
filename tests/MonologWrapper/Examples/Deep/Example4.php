<?php

namespace Smartling\Tests\MonologWrapper\Examples\Deep;

use Smartling\MonologWrapper\MonologWrapper;

class Example4 {

  public function doSomething($passClass = FALSE) {
    $logger = MonologWrapper::getLogger($passClass ? __CLASS__ : null);

    return [
      'logger' => $logger,
      'records' => [
        'debug' => $logger->debug('Example 4'),
        'info' => $logger->info('Example 4'),
        'notice' => $logger->notice('Example 4'),
        'warning' => $logger->warning('Example 4'),
        'error' => $logger->error('Example 4'),
        'critical' => $logger->critical('Example 4'),
        'alert' => $logger->alert('Example 4'),
        'emergency' => $logger->emergency('Example 4'),
      ]
    ];
  }

}
