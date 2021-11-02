<?php

namespace Smartling\Helpers\Serializers;

class SerializerJsonWithFallback implements SerializerInterface {
    public function getType(): string
    {
        return 'json';
    }

    public function serialize($data): string
    {
        return base64_encode(json_encode($data, JSON_FORCE_OBJECT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function unserialize($string): array
    {
        try {
            return json_decode(base64_decode($string), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            // not JSON, try with php built in unserialize()
            return unserialize(base64_decode($string));
        }
    }
}
