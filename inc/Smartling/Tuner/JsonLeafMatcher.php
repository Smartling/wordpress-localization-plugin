<?php

namespace Smartling\Tuner;

/**
 * Finds scalar leaves of decoded JSON that satisfy an extended JsonFieldRule.
 * Evaluated in PHP because JSONPath filters combined with recursive descent are unreliable in the bundled library.
 */
class JsonLeafMatcher
{
    /**
     * @return array<int, array{segments: array<int, string|int>, value: mixed}>
     */
    public function match(array $json, JsonFieldRule $rule): array
    {
        $result = [];
        $this->walk($json, $rule, [], [], $result);

        return $result;
    }

    /**
     * @param array<int, string|int> $segments
     */
    public function setValue(array &$json, array $segments, mixed $value): bool
    {
        $node = &$json;
        foreach ($segments as $segment) {
            if (!is_array($node) || !array_key_exists($segment, $node)) {
                return false;
            }
            $node = &$node[$segment];
        }
        $node = $value;

        return true;
    }

    /**
     * @param array<int, string|int> $segments
     */
    public function pathToString(array $segments): string
    {
        $path = '$';
        foreach ($segments as $segment) {
            if (is_int($segment)) {
                $path .= "[$segment]";
            } elseif (preg_match('~^[A-Za-z_]\w*$~', $segment) === 1) {
                $path .= ".$segment";
            } else {
                $path .= "['" . str_replace(['\\', "'"], ['\\\\', "\\'"], $segment) . "']";
            }
        }

        return $path;
    }

    /**
     * @param array<int, string|int> $segments
     * @param array<int, array> $objects object ancestors, outermost first
     */
    private function walk(mixed $node, JsonFieldRule $rule, array $segments, array $objects, array &$result): void
    {
        if (!is_array($node)) {
            if ($segments !== [] && $this->leafMatches($segments, $objects, $rule)) {
                $result[] = ['segments' => $segments, 'value' => $node];
            }
            return;
        }
        $isObject = !array_is_list($node);
        if ($isObject) {
            $objects[] = $node;
        }
        foreach ($node as $key => $child) {
            $this->walk($child, $rule, [...$segments, $key], $objects, $result);
        }
    }

    /**
     * @param array<int, string|int> $segments
     * @param array<int, array> $objects
     */
    private function leafMatches(array $segments, array $objects, JsonFieldRule $rule): bool
    {
        $suffix = $rule->getKeySuffix();
        $keys = array_values(array_filter($segments, 'is_string'));
        if (array_slice($keys, -count($suffix)) !== $suffix) {
            return false;
        }
        if ($rule->getWidgetType() !== '' && !$this->isInsideWidget($objects, $rule->getWidgetType())) {
            return false;
        }
        foreach ($rule->getConditions() as $condition) {
            $index = count($objects) - 1 - $condition['ancestor'];
            $value = $index >= 0 ? ($objects[$index][$condition['key']] ?? null) : null;
            if (!is_string($value) || $value !== $condition['value']) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, array> $objects
     */
    private function isInsideWidget(array $objects, string $widgetType): bool
    {
        foreach ($objects as $object) {
            if (($object['elType'] ?? null) === 'widget' && ($object['widgetType'] ?? null) === $widgetType) {
                return true;
            }
        }

        return false;
    }
}
