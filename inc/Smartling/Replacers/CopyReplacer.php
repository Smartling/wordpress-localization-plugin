<?php

namespace Smartling\Replacers;

use Smartling\Submissions\SubmissionEntity;

class CopyReplacer extends DoNothingContentReplacer
{
    public function getLabel(): string
    {
        return "Copy attribute value";
    }

    public function processAttributeOnDownload(mixed $originalValue, mixed $translatedValue, ?SubmissionEntity $submission): mixed
    {
        return $originalValue;
    }

    public function processAttributeOnUpload(mixed $value, ?SubmissionEntity $submission = null): string
    {
        return '';
    }
}
