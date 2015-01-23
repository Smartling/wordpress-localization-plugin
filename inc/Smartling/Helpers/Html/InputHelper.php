<?php

namespace Smartling\Helpers\Html;

class InputHelper extends AbstractHelper
{

    private $attributes;

    /**
     * @param array $source
     * @param string $namespace
     * @param string $name
     * @param string $value
     * @param array $attributes
     */
    public function __construct(array $source, $namespace, $name = '', $value = '', array $attributes = array())
    {
        $this->namespace = $namespace;
        $this->name = $name;
        $this->defaultValue = $value;
        $this->attributes = $attributes;

        parent::__construct($source);
    }

    /**
     * Renders HTML Element
     * @return string
     */
    public function render()
    {
        $attr = array(
            'name'      =>  $this->buildHtmlName(),
            'id'        =>  $this->buildHtmlName(),
            'value'     =>  $this->defaultValue,
        );

        $attr = array_merge($attr, $this->attributes);

        return $this->renderHTMLTag('input', $attr, '', true);

    }

}