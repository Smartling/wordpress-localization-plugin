<?php

namespace Smartling\ContentTypes\Elementor;

class ElementFactory {
    public const UNKNOWN_ELEMENT = 'unknown';
    private const ELEMENTS = 'Elements';
    /**
     * @var Element[]
     */
    private array $elements = [];

    public function __construct()
    {
        foreach (new \DirectoryIterator(__DIR__ . DIRECTORY_SEPARATOR . self::ELEMENTS) as $fileInfo) {
            if ($fileInfo->isFile() && $fileInfo->getExtension() === 'php') {
                $className = $fileInfo->getFileInfo()->getBasename('.php');
                try {
                    $element = new (implode('\\', [__NAMESPACE__, self::ELEMENTS, $className]));
                } catch (\Error) {
                    continue;
                }
                if ($element instanceof Element) {
                    $this->elements[$element->getType()] = $element;
                }
            }
        }
    }

    public function addElement(Element $element): void
    {
        $this->elements[$element->getType()] = $element;
    }

    public function fromArray(array $array): Element
    {
        foreach ($array['elements'] as &$element) {
            $element = $this->fromArray($element);
        }
        unset($element);

        $type = self::UNKNOWN_ELEMENT;

        if (array_key_exists('elType', $array)) {
            $type = $array['elType'] === 'widget' ? ($array['widgetType'] ?? self::UNKNOWN_ELEMENT) : $array['elType'];
        }

        return array_key_exists($type, $this->elements) ?
            $this->elements[$type]->fromArray($array) :
            $this->elements[self::UNKNOWN_ELEMENT]->fromArray($array);
    }
}
