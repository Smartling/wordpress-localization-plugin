<?php

namespace Smartling\Helpers;

class WpTransientCache implements Cache {
    private const DEFAULT_EXPIRATION = 86400;

    public function delete(string $key): bool
    {
        return delete_transient($key);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $result = get_transient($key); // returns boolean false if key doesn't exist

        return $result === false ? $default : $result;
    }

    public function set(string $key, mixed $data, ?int $expire = self::DEFAULT_EXPIRATION): bool
    {
        if (strlen($key) > 172) {
            // Longer names will silently fail by default
            throw new \InvalidArgumentException("Key must be 172 characters or fewer in length");
        }
        return set_transient($key, $data, $expire);
    }
}
