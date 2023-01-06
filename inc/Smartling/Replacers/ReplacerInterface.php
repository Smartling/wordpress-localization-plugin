<?php

namespace Smartling\Replacers;

use Smartling\Submissions\SubmissionEntity;

interface ReplacerInterface
{
    public function getLabel(): string;

    public function processOnDownload(mixed $originalValue, mixed $translatedValue, ?SubmissionEntity $submission):mixed;

    public function processOnUpload(mixed $value, ?SubmissionEntity $submission = null):mixed;
}
