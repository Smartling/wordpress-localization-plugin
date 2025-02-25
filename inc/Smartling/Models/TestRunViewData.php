<?php

namespace Smartling\Models;

readonly class TestRunViewData
{
    public function __construct(
        public array $blogs,
        public ?int $testBlogId,
        public int $new,
        public int $inProgress,
        public int $completed,
        public int $failed,
        public int $uploadCronLastFinishTime,
        public int $uploadCronIntervalSeconds,
    ) {
    }
}
