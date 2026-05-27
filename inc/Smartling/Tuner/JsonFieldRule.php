<?php

namespace Smartling\Tuner;

final class JsonFieldRule
{
    private const STRING_FORMAT = 'contentType="%s", metaKey="%s", propertyPath="%s", replacerId="%s"';

    public function __construct(
        private string $contentType,
        private string $metaKey,
        private string $propertyPath,
        private string $replacerId,
    ) {
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }

    public function getMetaKey(): string
    {
        return $this->metaKey;
    }

    public function getPropertyPath(): string
    {
        return $this->propertyPath;
    }

    public function getReplacerId(): string
    {
        return $this->replacerId;
    }

    public function toArray(): array
    {
        return [
            'contentType' => $this->contentType,
            'metaKey' => $this->metaKey,
            'propertyPath' => $this->propertyPath,
            'replacerId' => $this->replacerId,
        ];
    }

    public static function fromArray(array $data): self
    {
        foreach (['contentType', 'metaKey', 'propertyPath', 'replacerId'] as $key) {
            if (!array_key_exists($key, $data)) {
                throw new \InvalidArgumentException("Missing key in JsonFieldRule array: $key");
            }
        }

        return new self(
            (string)$data['contentType'],
            (string)$data['metaKey'],
            (string)$data['propertyPath'],
            (string)$data['replacerId'],
        );
    }

    public function __toString(): string
    {
        return sprintf(
            self::STRING_FORMAT,
            addslashes($this->contentType),
            addslashes($this->metaKey),
            addslashes($this->propertyPath),
            addslashes($this->replacerId),
        );
    }

    public static function fromString(string $string): self
    {
        $pattern = '~' . str_replace('%s', '(.*)', preg_quote(self::STRING_FORMAT, '~')) . '~';
        if (preg_match($pattern, stripslashes($string), $m) !== 1) {
            throw new \InvalidArgumentException("Cannot parse JsonFieldRule from: $string");
        }

        return new self($m[1], $m[2], $m[3], $m[4]);
    }
}
