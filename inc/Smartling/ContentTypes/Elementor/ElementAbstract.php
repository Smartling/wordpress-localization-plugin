<?php

namespace Smartling\ContentTypes\Elementor;

abstract class ElementAbstract implements Element {
    protected array $elements;
    protected string $id;
    protected array $raw;
    protected array $settings;
    protected string $type;

    public function __construct(array $array = [])
    {
        $this->elements = $array['elements'] ?? [];
        $this->id = $array['id'] ?? '';
        $this->raw = $array;
        $this->settings = $array['settings'] ?? [];
        $this->type = $array['elType'] ?? ElementFactory::UNKNOWN_ELEMENT;
    }

    public function fromArray(array $array): static
    {
        return new static($array);
    }

    public function getId(): string
    {
        return $this->id;
    }

    protected function getIntSettingByKey(string $key, array $settings): ?int
    {
        $setting = $this->getSettingByKey($key, $settings);

        return is_int($setting) ? $setting : null;
    }

    protected function getSettingByKey(string $key, array $settings): mixed
    {
        $parts = explode('/', $key);
        if (count($parts) > 1) {
            $path = array_shift($parts);

            return is_array($settings[$path] ?? '')
                ? $this->getSettingByKey(implode('/', $parts), $settings[$path])
                : null;
        }

        return $settings[$parts[0]] ?? null;
    }

    protected function getStringSettingByKey(string $key, array $settings): ?string
    {
        $setting = $this->getSettingByKey($key, $settings);

        return is_string($setting) ? $setting : null;
    }

    protected function getTranslatableStringsByKeys(array $keys, Element $element = null): array
    {
        if ($element === null) {
            $element = $this;
        }
        $result = [];
        foreach ($keys as $key) {
            $string = $this->getStringSettingByKey($key, $element->settings);
            if ($string !== null) {
                $result[$key] = $string;
            }
        }

        return $result;
    }

    public function toArray(): array
    {
        foreach ($this->raw['elements'] ?? [] as $index => $element) {
            if ($element instanceof Element) {
                $this->raw['elements'][$index] = $element->toArray();
            }
        }

        return $this->raw;
    }
}
