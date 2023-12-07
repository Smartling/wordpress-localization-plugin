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
        return [$this->id => count($return) > 0 ? array_replace(...$return) : $return];
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
}
