<?php

namespace Smartling\Processors;

use Psr\Log\LoggerInterface;
use Smartling\Bootstrap;
use Smartling\Exception\SmartlingInvalidFactoryArgumentException;
use Smartling\MonologWrapper\MonologWrapper;

/**
 * Class SmartlingFactoryAbstract
 *
 * @package Smartling\Processors
 */
abstract class SmartlingFactoryAbstract
{
    private $collection = [];

    /**
     * @return object[]
     */
    protected function getCollection()
    {
        return $this->collection;
    }

    private $defaultHandler;

    /**
     * @return object|null
     */
    public function getDefaultHandler()
    {
        return $this->defaultHandler;
    }

    /**
     * @param object $defaultHandler
     */
    public function setDefaultHandler($defaultHandler)
    {
        $this->defaultHandler = $defaultHandler;
    }

    private $allowDefault = false;

    /**
     * @return boolean
     */
    public function getAllowDefault()
    {
        return $this->allowDefault;
    }

    /**
     * @param boolean $allowDefault
     */
    public function setAllowDefault($allowDefault)
    {
        $this->allowDefault = $allowDefault;
    }

    protected $message = '';
    private $logger;


    /**
     * @return LoggerInterface
     */
    protected function getLogger()
    {
        return $this->logger;
    }

    /**
     * SmartlingFactoryAbstract constructor.
     */
    public function __construct()
    {
        $this->logger = MonologWrapper::getLogger(get_called_class());
    }

    /**
     * @param string $contentType
     * @param object $mapper
     * @param bool $force
     */
    public function registerHandler($contentType, $mapper, $force = false)
    {
        if (!array_key_exists($contentType, $this->collection) || $force) {
            $this->collection[$contentType] = $mapper;
        } else {
            $this->getLogger()->debug('Trying to register already existing handler for ' . $contentType);
        }
    }

    /**
     * @param string $contentType
     *
     * @return object
     * @throws SmartlingInvalidFactoryArgumentException
     */
    public function getHandler($contentType)
    {
        if (array_key_exists($contentType, $this->collection)) {
            return $this->collection[$contentType];
        }

        if (true === $this->getAllowDefault() && null !== $this->getDefaultHandler()) {
            return $this->getDefaultHandler();
        }

        $message = vsprintf($this->message, [$contentType, get_called_class()]);
        $this->getLogger()->error($message);
        throw new SmartlingInvalidFactoryArgumentException($message);
    }
}
