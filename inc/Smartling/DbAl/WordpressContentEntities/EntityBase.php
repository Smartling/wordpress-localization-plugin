<?php

namespace Smartling\DbAl\WordpressContentEntities;

use Smartling\WP\View\BulkSubmitScreenRow;

abstract class EntityBase implements Entity {
    public function getRelatedTypes(): array
    {
        return [];
    }
}
