<?php

namespace Smartling\Helpers\MetaFieldProcessor;

use Psr\Log\LoggerInterface;
use Smartling\Helpers\TranslationHelper;
use Smartling\MonologWrapper\MonologWrapper;

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
     * MetaFieldProcessorAbstract constructor.
     */
    public function __construct()
    {
        $this->logger = MonologWrapper::getLogger(get_called_class());
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