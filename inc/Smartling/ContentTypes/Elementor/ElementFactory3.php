<?php

namespace Smartling\ContentTypes\Elementor;

class ElementFactory3 {
    public const UNKNOWN_ELEMENT = 'unknown';
    private const ELEMENTS = 'Elements';
    /**
     * @var Element[]
     */
    protected array $elements = [];

    public function __construct()
    {
        $this->loadElements(__DIR__ . DIRECTORY_SEPARATOR . self::ELEMENTS);
    }

    protected function loadElements(string $directory): void
    {
        $namespace = basename($directory);
        foreach (new \DirectoryIterator($directory) as $fileInfo) {
            if ($fileInfo->isFile() && $fileInfo->getExtension() === 'php') {
                $className = $fileInfo->getFileInfo()->getBasename('.php');
                $element = new (implode('\\', [__NAMESPACE__, $namespace, $className]));
                if ($element instanceof Element) {
                    $this->elements[$element->getType()] = $element;
                }
            }
        }
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
