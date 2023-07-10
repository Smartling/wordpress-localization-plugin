<?php

namespace Smartling\Replacers;

use Smartling\Models\GutenbergBlock;
use Smartling\Submissions\SubmissionEntity;

interface ReplacerInterface
{
    public function getLabel(): string;

    public function processAttributeOnDownload(mixed $originalValue, mixed $translatedValue, ?SubmissionEntity $submission): mixed;

    public function processAttributeOnUpload(mixed $value, ?SubmissionEntity $submission = null): mixed;

    public function processContentOnDownload(GutenbergBlock $original, GutenbergBlock $translated, ?SubmissionEntity $submission): GutenbergBlock;
}
