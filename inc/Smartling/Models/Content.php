<?php

namespace Smartling\Models;

readonly class Content {
    public function __construct(
        public int $contentId,
        public string $contentType,
    ) {
    }
}
