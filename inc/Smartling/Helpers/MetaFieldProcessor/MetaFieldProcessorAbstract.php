<?php

namespace Smartling\Helpers\MetaFieldProcessor;
use Psr\Log\LoggerInterface;

/**
 * Class MetaFieldProcessorAbstract
 * @package Smartling\Helpers\MetaFieldProcessor
 */
abstract class MetaFieldProcessorAbstract implements MetaFieldProcessorInterface
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

    /**
     * MetaFieldProcessorAbstract constructor.
     *
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->setLogger($logger);
    }
}