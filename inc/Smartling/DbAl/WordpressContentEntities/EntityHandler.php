<?php

namespace Smartling\DbAl\WordpressContentEntities;

use Smartling\Exception\EntityNotFoundException;
use Smartling\Exception\SmartlingDbException;

interface EntityHandler {
    /**
     * @throws EntityNotFoundException
     */
    public function get(int $id): EntityInterface;

    /**
     * @return EntityInterface[]
     */
    public function getAll(int $limit = 0, int $offset = 0, string $orderBy = '', string $order = '', string $searchString = ''): array;

    public function getTitle(int $id): string;

    public function getTotal(): int;

    /**
     * @throws SmartlingDbException
     */
    public function set(EntityInterface $entity): int;
}
