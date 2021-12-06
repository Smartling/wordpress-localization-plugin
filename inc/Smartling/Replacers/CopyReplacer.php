<?php

namespace Smartling\Replacers;

use Smartling\Submissions\SubmissionEntity;

class CopyReplacer implements ReplacerInterface
{
    public function getLabel(): string
    {
        return "Copy attribute value";
    }

    /**
     * @param mixed $originalValue
     * @param mixed $translatedValue
     * @return mixed
     */
    public function processOnDownload($originalValue, $translatedValue, ?SubmissionEntity $submission)
    {
        return $originalValue;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    public function processOnUpload($value, ?SubmissionEntity $submission = null): string
    {
        return '';
    }
}
