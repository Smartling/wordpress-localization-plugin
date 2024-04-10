<?php

namespace Smartling\MonologWrapper\Logger;

use InvalidArgumentException;
use Smartling\Models\LoggerWithStringContext;
use Smartling\Vendor\Monolog\Logger;
use Smartling\Vendor\Psr\Log\LogLevel;

class LevelLogger extends Logger implements LoggerWithStringContext
{
    private array $context = [];

    /**
     * Do not record messages lower than this level.
     */
    private int $level;

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

    public function alterContext(array $context): void
    {
        foreach ($this->context as $existingKey => $_) {
            if (array_key_exists($existingKey, $context)) {
                $this->context[$existingKey] = $context[$existingKey];
            }
        }
    }

    public function withStringContext(array $context, callable $callable): mixed
    {
        foreach ($context as $key => $value) {
            $this->context[$key] = $value;
        }
        try {
            return $callable();
        } finally {
            foreach (array_keys($context) as $key) {
                unset($this->context[$key]);
            }
        }
    }

    private function addStringContext(string $message): string {
        $strings = [$message];
        foreach ($this->context as $key => $value) {
            $strings[] = $key . '="' . addslashes(is_scalar($value) ? $value : 'Non-scalar: ' . json_encode($value)) . '"';
        }

        return implode(', ', $strings);
    }

    public function debug($message, array $context = [])
    {
        return parent::debug($this->addStringContext($message), $context);
    }

    public function info($message, array $context = [])
    {
        return parent::info($this->addStringContext($message), $context);
    }

    public function notice($message, array $context = [])
    {
        return parent::notice($this->addStringContext($message), $context);
    }

    public function warning($message, array $context = [])
    {
        return parent::warning($this->addStringContext($message), $context);
    }

    public function error($message, array $context = [])
    {
        return parent::error($this->addStringContext($message), $context);
    }

    public function alert($message, array $context = [])
    {
        return parent::alert($this->addStringContext($message), $context);
    }

    public function critical($message, array $context = [])
    {
        return parent::critical($this->addStringContext($message), $context);
    }

    public function emergency($message, array $context = [])
    {
        return parent::emergency($this->addStringContext($message), $context);
    }
}
