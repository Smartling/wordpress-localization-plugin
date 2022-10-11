<?php

namespace Smartling\Tuner;

interface ManagerInterface
{
    /**
     * @return array[]
     */
    public function listItems(): array;

    public function updateItem(string $id, array $data): void;

    public function removeItem(string $id): void;

    public function getItem(string $id);
}
