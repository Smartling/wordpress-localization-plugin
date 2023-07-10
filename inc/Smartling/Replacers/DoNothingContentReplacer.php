<?php

namespace Smartling\Replacers;

use Smartling\Models\GutenbergBlock;
use Smartling\Submissions\SubmissionEntity;

class DoNothingContentReplacer implements ReplacerInterface {

    public function getLabel(): string
    {
        return 'Do nothing';
    }

    public function processAttributeOnDownload(mixed $originalValue, mixed $translatedValue, ?SubmissionEntity $submission): mixed
    {
        return $translatedValue;
    }

    public function processAttributeOnUpload(mixed $value, ?SubmissionEntity $submission = null): mixed
    {
        return $value;
    }

    public function processContentOnDownload(GutenbergBlock $original, GutenbergBlock $translated, ?SubmissionEntity $submission): GutenbergBlock
    {
        return $translated;
    }
}
