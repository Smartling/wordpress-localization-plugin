<?php

namespace Smartling\Replacers;

use Smartling\Submissions\SubmissionEntity;

class ExcludeReplacer implements ReplacerInterface
{
    public function getLabel(): string
    {
        return "Exclude attribute";
    }

    /**
     * @param mixed $originalValue
     * @param mixed $translatedValue
     * @return null
     */
    public function processOnDownload($originalValue, $translatedValue, ?SubmissionEntity $submission)
    {
        return null;
    }

    /**
     * @param mixed $value
     */
    public function processOnUpload($value, ?SubmissionEntity $submission = null): string
    {
        return '';
    }
}
