<?php

namespace Smartling\DbAl\WordpressContentEntities;

use Smartling\WP\View\BulkSubmitScreenRow;

abstract class EntityBase implements Entity {
    public function getRelatedContentTypes(): array
    {
        return [];
    }
}
