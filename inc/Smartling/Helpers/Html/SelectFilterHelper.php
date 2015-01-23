<?php

namespace Smartling\Helpers\Html;


class SelectFilterHelper extends AbstractHelper
{
    private $label;
    
    private $optionList;

    private $selectedOption;

    /**
     * @param array $source
     * @param $namespace
     * @param $name
     * @param $label
     * @param $optionList
     * @param $defaultOption
     */
    public function __construct(array $source, $namespace, $name, $label, $optionList, $defaultOption)
    {
        parent::__construct($source);

        $this->namespace = $namespace;
        $this->name = $name;
        $this->label = $label;
        $this->optionList = $optionList;
        $this->defaultValue = $defaultOption;
        $this->selectedOption = $this->getFromSource($this->buildHtmlName(), $this->defaultValue);
    }

    private function renderOptionsBlock()
    {
        $renderedOptions = array();
        foreach($this->optionList as $val => $lbl) {
            $attributes = array('value' => $val);
            if ($this->selectedOption == $val || (is_null($this->selectedOption) && $this->defaultValue == $val)){
                $attributes['selected'] = 'selected';
            }
            $renderedOptions[] = $this->renderHTMLTag('option', $attributes, $lbl);
        }

        return implode('', $renderedOptions);
    }

    /**
     * @return string
     */
    public function render()
    {
        $html_id = vsprintf('%s-%s', array($this->namespace, $this->name));

        $renderedFilterHtml =
            $this->renderHTMLTag('label', array('for' => $html_id), $this->label)
            . $this->renderHTMLTag('select', array('id' => $html_id, 'name' => $html_id),$this->renderOptionsBlock());

        return $renderedFilterHtml;
    }
}