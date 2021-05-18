<?php

namespace Smartling\Replacers;

use Smartling\Submissions\SubmissionEntity;

interface ReplacerInterface
{
    public function getLabel(): string;

    /**
     * @param mixed $value
     * @return mixed
     */
    public function processOnDownload(SubmissionEntity $submission, $value);
}
