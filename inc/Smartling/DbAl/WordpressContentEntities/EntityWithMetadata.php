<?php

namespace Smartling\DbAl\WordpressContentEntities;

interface EntityWithMetadata extends Entity {
    public function getMetadata(): array;

    public function setMetaTag(string $key, mixed $value): void;
}
