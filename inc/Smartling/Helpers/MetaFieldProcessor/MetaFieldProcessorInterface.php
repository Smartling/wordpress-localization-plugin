<?php

namespace Smartling\Helpers\MetaFieldProcessor;

use Psr\Log\LoggerInterface;
use Smartling\Helpers\TranslationHelper;
use Smartling\Submissions\SubmissionEntity;

/**
 * Interface MetaFieldProcessorInterface
 * @package Smartling\Helpers\MetaFieldProcessor
 */
interface MetaFieldProcessorInterface
{
    /**
     * @return string
     */
    public function getFieldName();

    /**
     * @param SubmissionEntity $submission
     * @param mixed            $value
     *
     * @return mixed
     */
    public function processFieldValue(SubmissionEntity $submission, $value);

    /**
     * MetaFieldProcessorInterface constructor.
     *
     * @param LoggerInterface   $logger
     * @param TranslationHelper $translationHelper
     * @param string            $fieldName
     */
    public function __construct(LoggerInterface $logger, TranslationHelper $translationHelper, $fieldName);
}