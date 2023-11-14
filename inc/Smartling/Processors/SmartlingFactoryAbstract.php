<?php

namespace Smartling\Processors;

use Smartling\Exception\SmartlingInvalidFactoryArgumentException;
use Smartling\Helpers\LoggerSafeTrait;

abstract class SmartlingFactoryAbstract
{
    use LoggerSafeTrait;

    protected array $collection = [];

    protected ?object $defaultHandler;

    protected bool $allowDefault;

    protected string $message = '';

    public function __construct(bool $allowDefault = false, ?object $defaultHandler = null)
    {
        $this->allowDefault = $allowDefault;
        $this->defaultHandler = $defaultHandler;
    }

    public function registerHandler(string $contentType, object $mapper, bool $force = false): void
    {
        if (!array_key_exists($contentType, $this->collection)) {
            $this->collection[$contentType] = $mapper;
        } elseif (true === $force) {
            unset ($this->collection[$contentType]);
            $this->registerHandler($contentType, $mapper);
        }
    }

    /**
     * @throws SmartlingInvalidFactoryArgumentException
     */
    public function getHandler(string $contentType): object
    {
        if (array_key_exists($contentType, $this->collection)) {
            return $this->collection[$contentType];
        }

        if (true === $this->allowDefault && null !== $this->defaultHandler) {
            return $this->defaultHandler;
        }

        throw new SmartlingInvalidFactoryArgumentException(sprintf($this->message, $contentType, get_called_class()));
    }

    public function getCollection(): array {
         return $this->collection;
    }
}
