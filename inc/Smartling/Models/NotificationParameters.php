<?php

namespace Smartling\Models;

readonly class NotificationParameters
{
    public function __construct(
        public string $contentId,
        public string $message,
        public string $projectId,
        public string $severity,
    ) {
    }
}
