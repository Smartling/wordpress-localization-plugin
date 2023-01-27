<?php

namespace Smartling\DbAl\WordpressContentEntities;

interface EntityWithMetadata extends EntityInterface {
    public function getMetadata(): array;
}
