<?php

namespace Smartling\Helpers\MetaFieldProcessor;
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
}