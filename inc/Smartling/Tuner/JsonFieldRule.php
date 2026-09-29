<?php

namespace Smartling\Tuner;

final class JsonFieldRule
{
    public const MAX_CONDITIONS = 5;
    public const MAX_STRING_LENGTH = 256;
    private const EXTENDED_PATH_PATTERN = '~^\$\.\.[A-Za-z_][\w-]*(\.[A-Za-z_][\w-]*)*$~';
    private const WIDGET_TYPE_PATTERN = '~^[A-Za-z0-9_-]{1,64}$~';

    /**
     * @param array<int, array{ancestor: int, key: string, value: string}> $conditions
     */
    public function __construct(
        private string $metaKey,
        private string $propertyPath,
        private string $replacerId,
        private string $widgetType = '',
        private array $conditions = [],
    ) {
        if ($this->widgetType !== '' && preg_match(self::WIDGET_TYPE_PATTERN, $this->widgetType) !== 1) {
            throw new \InvalidArgumentException('Invalid widgetType');
        }
        $this->conditions = self::normalizeConditions($this->conditions);
        if ($this->isExtended() && preg_match(self::EXTENDED_PATH_PATTERN, $this->propertyPath) !== 1) {
            throw new \InvalidArgumentException(
                'Rules with a widget or conditions must use a path in the form $..key.subkey',
            );
        }
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

    public function getWidgetType(): string
    {
        return $this->widgetType;
    }

    /**
     * @return array<int, array{ancestor: int, key: string, value: string}>
     */
    public function getConditions(): array
    {
        return $this->conditions;
    }

    /**
     * Extended rules are evaluated by JsonLeafMatcher, the rest by JSONPath
     */
    public function isExtended(): bool
    {
        return $this->widgetType !== '' || $this->conditions !== [];
    }

    /**
     * @return string[] object keys the path ends with, e.g. $..a.b => [a, b]
     */
    public function getKeySuffix(): array
    {
        return explode('.', substr($this->propertyPath, 3));
    }

    public function toArray(): array
    {
        $result = [
            'metaKey' => $this->metaKey,
            'propertyPath' => $this->propertyPath,
            'replacerId' => $this->replacerId,
        ];
        if ($this->widgetType !== '') {
            $result['widgetType'] = $this->widgetType;
        }
        if ($this->conditions !== []) {
            $result['conditions'] = $this->conditions;
        }

        return $result;
    }

    public static function fromArray(array $data): self
    {
        foreach (['metaKey', 'propertyPath', 'replacerId'] as $key) {
            if (!array_key_exists($key, $data)) {
                throw new \InvalidArgumentException("Missing key in JsonFieldRule array: $key");
            }
        }

        return new self(
            (string)$data['metaKey'],
            (string)$data['propertyPath'],
            (string)$data['replacerId'],
            (string)($data['widgetType'] ?? ''),
            is_array($data['conditions'] ?? null) ? $data['conditions'] : [],
        );
    }

    /**
     * @return array<int, array{ancestor: int, key: string, value: string}>
     */
    private static function normalizeConditions(array $conditions): array
    {
        if (count($conditions) > self::MAX_CONDITIONS) {
            throw new \InvalidArgumentException('Too many conditions, maximum is ' . self::MAX_CONDITIONS);
        }
        $result = [];
        foreach ($conditions as $condition) {
            if (!is_array($condition)
                || !isset($condition['ancestor'], $condition['key'], $condition['value'])
                || !is_numeric($condition['ancestor'])
                || !is_string($condition['key'])
                || !is_string($condition['value'])
            ) {
                throw new \InvalidArgumentException('Invalid condition, expected ancestor, key and value');
            }
            $ancestor = (int)$condition['ancestor'];
            if ($ancestor < 0 || $ancestor > 20) {
                throw new \InvalidArgumentException('Condition ancestor must be between 0 and 20');
            }
            if ($condition['key'] === ''
                || strlen($condition['key']) > self::MAX_STRING_LENGTH
                || strlen($condition['value']) > self::MAX_STRING_LENGTH
            ) {
                throw new \InvalidArgumentException('Condition key or value is empty or too long');
            }
            $result[] = ['ancestor' => $ancestor, 'key' => $condition['key'], 'value' => $condition['value']];
        }

        return $result;
    }
}
