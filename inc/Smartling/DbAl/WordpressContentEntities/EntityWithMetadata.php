<?php

namespace Smartling\DbAl\WordpressContentEntities;

interface EntityWithMetadata extends Entity {
    public function getMetadata(): array;
}
