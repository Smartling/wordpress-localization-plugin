<?php

namespace Smartling\Replacers;

use Smartling\Submissions\SubmissionEntity;

class CopyReplacer implements ReplacerInterface
{
    public function getLabel(): string
    {
        return "Copy attribute value";
    }

    public function processOnDownload(mixed $originalValue, mixed $translatedValue, ?SubmissionEntity $submission): mixed
    {
        return $originalValue;
    }

    public function processOnUpload(mixed $value, ?SubmissionEntity $submission = null): string
    {
        return '';
    }
}
