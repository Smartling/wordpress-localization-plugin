<?php

namespace Smartling\Replacers;

use Smartling\Submissions\SubmissionEntity;

class ExcludeReplacer extends DoNothingContentReplacer
{
    public function getLabel(): string
    {
        return "Exclude attribute";
    }

    public function processAttributeOnDownload(mixed $originalValue, mixed $translatedValue, ?SubmissionEntity $submission): mixed
    {
        return null;
    }

    public function processAttributeOnUpload(mixed $value, ?SubmissionEntity $submission = null): string
    {
        return '';
    }
}
