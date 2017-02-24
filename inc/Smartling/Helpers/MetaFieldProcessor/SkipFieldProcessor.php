<?php

namespace Smartling\Helpers\MetaFieldProcessor;

use Smartling\Submissions\SubmissionEntity;

/**
 * Class SkipFieldProcessor
 * @package Smartling\Helpers\MetaFieldProcessor
 */
class SkipFieldProcessor extends MetaFieldProcessorAbstract
{

    /**
     * BaseFieldProcessorAbstract constructor.
     *
     * @param string $fieldRegexp
     */
    public function __construct($fieldRegexp)
    {
        $this->setFieldRegexp($fieldRegexp);
    }

    /**
     * @param SubmissionEntity $submission
     * @param string           $fieldName
     * @param mixed            $value
     *
     * @return array|mixed
     */
    public function processFieldPostTranslation(SubmissionEntity $submission, $fieldName, $value)
    {
        return '';
    }

    /**
     * @param SubmissionEntity $submission
     * @param string           $fieldName
     * @param mixed            $value
     * @param array            $collectedFields
     *
     * @return mixed or empty string (to skip translation)
     */
    public function processFieldPreTranslation(SubmissionEntity $submission, $fieldName, $value, array $collectedFields)
    {
        return '';
    }
}