<?php

namespace Smartling\Helpers\MetaFieldProcessor;

use Psr\Log\LoggerInterface;
use Smartling\Helpers\TranslationHelper;

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
     * @var string
     */
    private $fieldName;

    /**
     * @var TranslationHelper
     */
    private $translationHelper;

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
     * @return string
     */
    public function getFieldName()
    {
        return $this->fieldName;
    }

    /**
     * @param string $fieldName
     */
    public function setFieldName($fieldName)
    {
        $this->fieldName = $fieldName;
    }

    /**
     * @return TranslationHelper
     */
    public function getTranslationHelper()
    {
        return $this->translationHelper;
    }

    /**
     * @param TranslationHelper $translationHelper
     */
    public function setTranslationHelper($translationHelper)
    {
        $this->translationHelper = $translationHelper;
    }

    /**
     * MetaFieldProcessorInterface constructor.
     *
     * @param LoggerInterface   $logger
     * @param TranslationHelper $translationHelper
     * @param string            $fieldName
     */
    public function __construct(LoggerInterface $logger, TranslationHelper $translationHelper, $fieldName)
    {
        $this->setLogger($logger);
        $this->setTranslationHelper($translationHelper);
        $this->setFieldName($fieldName);
    }
}