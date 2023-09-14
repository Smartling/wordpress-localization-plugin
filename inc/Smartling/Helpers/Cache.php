<?php

namespace Smartling\Helpers;

interface Cache {

    public function delete(string $key): bool;

    public function get(string $key): mixed;

    public function set(string $key, mixed $data, ?int $expire = null): bool;
}
