<?php

namespace Smartling\Extensions;

use Smartling\Services\HandlerManager;
use Smartling\Submissions\SubmissionEntity;

interface StringHandler {
    public function handle(string $string, ?HandlerManager $handlerManager, ?SubmissionEntity $submission): string;
}
