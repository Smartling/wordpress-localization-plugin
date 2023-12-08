<?php

namespace Smartling\ContentTypes\Elementor\Elements;

use Smartling\ContentTypes\Elementor\Element;
use Smartling\ContentTypes\Elementor\ElementFactory;
use Smartling\Helpers\ArrayHelper;

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
        $keys = ['background_image/alt', 'text'];
        foreach ($this->elements as $element) {
            if ($element instanceof Element) {
                $return[] = $element->getTranslatableStrings();
                $stringsFromCommonKeys = $this->getTranslatableStringsByKeys($keys, $element);
                if (count($stringsFromCommonKeys) > 0) {
                    $return[] = [$element->getId() => $stringsFromCommonKeys];
                }
            }
        }
        $return = count($return) > 0 ? (new ArrayHelper())->arrayMergePreserveKeys(...$return) : $return;

        $return += $this->getTranslatableStringsByKeys($keys);

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

    public function getSettingByKey(string $key, array $settings): ?string
    {
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

    public function getTranslatableStringsByKeys(array $keys, Element $element = null): array
    {
        if ($element === null) {
            $element = $this;
        }
        $result = [];
        foreach ($keys as $key) {
            $string = $this->getSettingByKey($key, $element->settings);
            if ($string !== null) {
                $result[$key] = $string;
            }
        }

        return $result;
    }
}
