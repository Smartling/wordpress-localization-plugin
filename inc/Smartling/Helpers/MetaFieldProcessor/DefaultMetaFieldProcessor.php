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
     * @param mixed            $value
     *
     * @return mixed
     */
    public function processFieldValue(SubmissionEntity $submission, $value)
    {
        return $value;
    }
}