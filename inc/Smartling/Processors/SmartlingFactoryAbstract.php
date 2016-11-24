<?php

namespace Smartling\Processors;

use Psr\Log\LoggerInterface;
use Smartling\Bootstrap;
use Smartling\Exception\SmartlingInvalidFactoryArgumentException;

/**
 * Class SmartlingFactoryAbstract
 *
 * @package Smartling\Processors
 */
abstract class SmartlingFactoryAbstract
{

    /**
     * Collection of handlers
     *
     * @var array
     */
    private $collection = [];

    /**
     * @return array
     */
    protected function getCollection()
    {
        return $this->collection;
    }

    /**
     * @var null
     */
    private $defaultHandler = null;

    /**
     * @return null
     */
    public function getDefaultHandler()
    {
        return $this->defaultHandler;
    }

    /**
     * @param null $defaultHandler
     */
    public function setDefaultHandler($defaultHandler)
    {
        $this->defaultHandler = $defaultHandler;
    }

    /**
     * @var bool
     */
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

    /**
     * @var string
     */
    protected $message = '';

    /**
     * @var LoggerInterface
     */
    private $logger;


    /**
     * @return LoggerInterface
     */
    protected function getLogger()
    {
        return $this->logger;
    }

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param          $contentType
     * @param          $mapper
     * @param bool     $force
     */
    public function registerHandler($contentType, $mapper, $force = false)
    {
        if (!array_key_exists($contentType, $this->collection)) {
            $this->collection[$contentType] = $mapper;
        } elseif (true === $force) {
            unset ($this->collection[$contentType]);
            $this->registerHandler($contentType, $mapper);
        }
    }

    /**
     * @param $contentType
     *
     * @return object
     * @throws SmartlingInvalidFactoryArgumentException
     */
    public function getHandler($contentType)
    {
        if (array_key_exists($contentType, $this->collection)) {
            return $this->collection[$contentType];
        } else {
            if (true === $this->getAllowDefault() && null !== $this->getDefaultHandler()) {
                return $this->getDefaultHandler();
            } else {
                $message = vsprintf($this->message, [$contentType, get_called_class()]);
                $this->getLogger()
                     ->error($message);
                throw new SmartlingInvalidFactoryArgumentException($message);
            }
        }
    }
}