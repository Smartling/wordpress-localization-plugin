<?php

namespace Smartling\Replacers;

use Smartling\Submissions\SubmissionEntity;

class ExcludeReplacer implements ReplacerInterface
{
    public function getLabel(): string
    {
        return "Exclude attribute";
    }

    public function processOnDownload(mixed $originalValue, mixed $translatedValue, ?SubmissionEntity $submission): mixed
    {
        return null;
    }

    public function processOnUpload(mixed $value, ?SubmissionEntity $submission = null): string
    {
        return '';
    }
}
