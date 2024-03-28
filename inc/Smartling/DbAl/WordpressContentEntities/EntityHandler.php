<?php

namespace Smartling\DbAl\WordpressContentEntities;

use Smartling\Exception\EntityNotFoundException;
use Smartling\Exception\SmartlingDbException;

interface EntityHandler {
    /**
     * @throws EntityNotFoundException
     */
    public function get(mixed $id): Entity;

    /**
     * @return Entity[]
     */
    public function getAll(
        int $limit = 0,
        int $offset = 0,
        string $orderBy = '',
        string $order = '',
        string $searchString = '',
        array $ids = [],
    ): array;

    public function getTotal(): int;

    /**
     * @throws SmartlingDbException
     */
    public function set(Entity $entity): int;
}
