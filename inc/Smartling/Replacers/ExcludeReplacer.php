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
     */
    public function processOnDownload(SubmissionEntity $submission, $originalValue, $translatedValue): void
    {
    }

    /**
     * @param mixed $value
     */
    public function processOnUpload(SubmissionEntity $submission, $value): void
    {
    }
}
