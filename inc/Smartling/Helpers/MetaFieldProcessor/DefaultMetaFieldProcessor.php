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
     * @return string
     */
    public function getFieldName()
    {
        return '';
    }

    /**
     * @param SubmissionEntity $submission
     * @param mixed            $value
     *
     * @return mixed
     */
    public function processFieldValue(SubmissionEntity $submission, $value)
    {
        return $value;
    }
}