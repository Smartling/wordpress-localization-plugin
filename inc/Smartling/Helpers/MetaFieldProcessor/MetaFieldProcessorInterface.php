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
    public function getFieldRegexp();

    /**
     * @param SubmissionEntity $submission
     * @param string           $fieldName
     * @param mixed            $value
     *
     * @return mixed
     */
    public function processFieldPostTranslation(SubmissionEntity $submission, $fieldName, $value);

    /**
     * @param string $fieldName
     * @param mixed  $value
     * @param array  $collectedFields
     *
     * @return mixed or empty string (to skip translation)
     */
    public function processFieldPreTranslation($fieldName, $value, array $collectedFields);
}