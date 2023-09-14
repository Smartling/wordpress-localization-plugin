<?php

namespace Smartling\Helpers;

class WpObjectCache implements Cache {
    private const GROUP = "smartling";

    public function delete(string $key): bool
    {
        return wp_cache_delete($key, self::GROUP);
    }

    public function flush(): bool
    {
        return wp_cache_flush();
    }

    public function get(string $key): mixed
    {
        return wp_cache_get($key, self::GROUP);
    }

    public function set(string $key, mixed $data, ?int $expire = null): bool
    {
        return wp_cache_set($key, $data, self::GROUP, $expire);
    }
}
