<?php

namespace Smartling\Helpers\MetaFieldProcessor;

use Smartling\Helpers\Parsers\IntegerParser;
use Smartling\Helpers\WordpressContentTypeHelper;
use Smartling\Submissions\SubmissionEntity;

/**
 * Class ReferencedPageProcessor
 * @package Smartling\Helpers\MetaFieldProcessor
 */
class ReferencedPageProcessor extends ReferencedContentProcessor
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
        $newId = parent::processFieldPostTranslation($submission, $fieldName, $value);

        return $newId;
    }
}