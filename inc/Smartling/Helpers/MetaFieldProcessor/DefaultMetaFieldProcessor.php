<?php

namespace Smartling\Helpers\MetaFieldProcessor;
use Psr\Log\LoggerInterface;

/**
 * Class DefaultMetaFieldProcessor
 * @package Smartling\Helpers\MetaFieldProcessor
 */
class DefaultMetaFieldProcessor implements MetaFieldProcessorInterface
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger($logger)
    {
        $this->logger = $logger;
    }

    public function __construct(LoggerInterface $logger)
    {
        $this->setLogger($logger);
    }

    /**
     * @return string
     */
    public function getFieldName()
    {
        return '';
    }

    /**
     * @param mixed $value
     *
     * @return mixed
     */
    public function processFieldValue($value)
    {
        return $value;
    }
}