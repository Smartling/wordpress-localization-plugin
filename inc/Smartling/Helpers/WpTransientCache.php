<?php

namespace Smartling\Helpers;

class WpTransientCache implements Cache {
    private const DEFAULT_EXPIRATION = 86400;

    public function delete(string $key): bool
    {
        return delete_transient($key);
    }

    public function get(string $key): mixed
    {
        return get_transient($key);
    }

    public function set(string $key, mixed $data, ?int $expire = self::DEFAULT_EXPIRATION): bool
    {
        return set_transient($key, $data, $expire);
    }
}
