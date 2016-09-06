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
    private $fieldRegexp;

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
    public function getFieldRegexp()
    {
        return $this->fieldRegexp;
    }

    /**
     * @param string $fieldRegexp
     */
    public function setFieldRegexp($fieldRegexp)
    {
        $this->fieldRegexp = $fieldRegexp;
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
}