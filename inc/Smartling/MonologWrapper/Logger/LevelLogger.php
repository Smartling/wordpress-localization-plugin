<?php

namespace Smartling\MonologWrapper\Logger;

use InvalidArgumentException;
use Smartling\Helpers\LogContextMixinHelper;
use Smartling\Models\LoggerWithStringContext;
use Smartling\Vendor\Monolog\Logger;
use Smartling\Vendor\Psr\Log\LogLevel;

class LevelLogger extends Logger implements LoggerWithStringContext
{

    /**
     * Do not record messages lower than this level.
     * @var int level
     */
    private $level = Logger::DEBUG;

    public function __construct(string $name, string $level = LogLevel::DEBUG, array $handlers = [], array $processors = [])
    {
        parent::__construct($name, $handlers, $processors);

        $level = strtoupper($level);

        if (!in_array($level, static::$levels, true)) {
            throw new InvalidArgumentException('Level "' . $level . '" is not defined, use one of: ' .
                                               implode(', ', array_values(static::$levels)));
        }

        $this->level = array_search($level, static::$levels);
    }

    /**
     * {@inheritdoc}
     */
    public function addRecord($level, $message, array $context = [])
    {
        if ($level >= $this->level) {
            return parent::addRecord($level, $message, $context);
        }

        return false;
    }

    public function withStringContext(array $context, callable $callable): mixed
    {
        foreach ($context as $key => $value) {
            LogContextMixinHelper::addToStringContext($key, $value);
        }
        try {
            return $callable();
        } finally {
            foreach (array_keys($context) as $key) {
                LogContextMixinHelper::removeFromStringContext($key);
            }
        }
    }
}
