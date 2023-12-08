<?php

namespace Smartling\ContentTypes\Elementor\Elements;

use Smartling\ContentTypes\Elementor\Element;
use Smartling\ContentTypes\Elementor\ElementFactory;

class Unknown implements Element {
    public function __construct(
        protected string $id = '',
        protected string $type = ElementFactory::UNKNOWN_ELEMENT,
        protected array $settings = [],
        protected array $elements = [],
    ) {
    }

    public function fromArray(array $array): static
    {
        return new static($array['id'], $array['elType'], $array['settings'], $array['elements']);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getRelated(): array
    {
        $return = [];
        foreach ($this->elements as $element) {
            if ($element instanceof Element) {
                $return = $element->getRelated();
            }
        }
        return [$this->id => $return];
    }

    public function getTranslatableStrings(): array
    {
        $return = [];
        foreach ($this->elements as $element) {
            if ($element instanceof Element) {
                $return[] = $element->getTranslatableStrings();
            }
        }
        $return = count($return) > 0 ? array_replace(...$return) : $return;

        $return += $this->getTranslatableStringsByKeys(['background_image/alt']);

        return [$this->id => $return];
    }

    public function getType(): string
    {
        return ElementFactory::UNKNOWN_ELEMENT;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'elType' => $this->type,
            'settings' => $this->settings,
            'elements' => $this->elements,
        ];
    }

    public function getSettingByKey(string $key, array $settings = null): ?string
    {
        if ($settings === null) {
            $settings = $this->settings;
        }
        $parts = explode('/', $key);
        if (count($parts) > 1) {
            $path = array_shift($parts);

            return array_key_exists($path, $settings) ? $this->getSettingByKey(implode('/', $parts), $settings[$path]) : null;
        }

        if (!array_key_exists($parts[0], $settings)) {
            return null;
        }

        $setting = $settings[$parts[0]];

        return is_string($setting) ? $setting : null;
    }

    public function getTranslatableStringsByKeys(array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $string = $this->getSettingByKey($key);
            if ($string !== null) {
                $result[$key] = $string;
            }
        }

        return $result;
    }
}
