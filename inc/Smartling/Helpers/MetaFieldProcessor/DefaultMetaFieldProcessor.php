<?php

namespace Smartling\Helpers\MetaFieldProcessor;

use Smartling\Submissions\SubmissionEntity;

/**
 * Class DefaultMetaFieldProcessor
 * @package Smartling\Helpers\MetaFieldProcessor
 */
class DefaultMetaFieldProcessor extends MetaFieldProcessorAbstract
{
    /**
     * @param SubmissionEntity $submission
     * @param string           $fieldName
     * @param mixed            $value
     *
     * @return mixed
     */
    public function processFieldPostTranslation(SubmissionEntity $submission, $fieldName, $value)
    {
        return $value;
    }

    /**
     * @param string $fieldName
     * @param mixed  $value
     * @param array  $collectedFields
     *
     * @return mixed
     */
    public function processFieldPreTranslation($fieldName, $value, array $collectedFields)
    {
        return $value;
    }
}