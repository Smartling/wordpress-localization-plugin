<?php

namespace Smartling\Models;

readonly class JobInformation
{
    public function __construct(
        public string $id,
        public bool $authorize,
        public string $name,
        public string $description,
        public string $dueDate,
        public string $timeZone,
    ) {
    }
}
