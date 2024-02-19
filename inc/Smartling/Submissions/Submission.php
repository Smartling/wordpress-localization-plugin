<?php

namespace Smartling\Submissions;

interface Submission {
    public function getContentType(): string;

    public function getSourceBlogId(): int;

    public function getSourceId(): int;

    public function getTargetBlogId(): int;

    public function getTargetId(): int;
}
