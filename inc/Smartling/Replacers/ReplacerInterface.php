<?php

namespace Smartling\Replacers;

use Smartling\Submissions\SubmissionEntity;

interface ReplacerInterface
{
    public function getLabel(): string;

    /**
     * @param mixed $originalValue
     * @param mixed $translatedValue
     * @return mixed
     */
    public function processOnDownload($originalValue, $translatedValue, ?SubmissionEntity $submission);

    /**
     * @param mixed $value
     * @return mixed
     */
    public function processOnUpload($value, ?SubmissionEntity $submission = null);
}
