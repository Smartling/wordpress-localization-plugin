<?php

namespace Smartling\Helpers\Html;

/**
 * Class AbstractHelper
 * @package Smartling\Helpers\Html
 */
abstract class AbstractHelper
{
    private $feedbackSource;

    protected $name;

    protected $namespace;

    protected $defaultValue;

    /**
     * Retrieves value by key name from request
     * @param $key
     * @param null $defaultValue
     * @return null
     */
    protected function getFromSource($key, $defaultValue = null)
    {
        $value = (isset($this->feedbackSource[$key]) ? $this->feedbackSource[$key] : $this->defaultValue);

        return $value;
    }

    /**
     * Constructor
     * @param array $source
     */
    public function __construct(array $source)
    {
        $this->feedbackSource = $source;
    }

    /**
     * Renders HTML tag
     * @param string $tagName
     * @param array $attributes
     * @param string $content
     * @param bool $shortStyle  if true will produce tag like <img />
     * @return string
     */
    protected function renderHTMLTag($tagName, array $attributes, $content, $shortStyle = false)
    {
        $_tmpArray = array();

        foreach ($attributes as $attribute => $value) {
            $_tmpArray[] =  vsprintf('%s="%s"', array($attribute, $value));
        }

        $tagString = implode(' ', $_tmpArray);

        if (empty($content) && true === $shortStyle) {
            $tagString = vsprintf("<%s %s />", array($tagName, $tagString));
        } else {
            $tagString = vsprintf("<%s %s>%s</%s>", array($tagName, $tagString, $content, $tagName));
        }

        return $tagString;
    }

    /**
     * Generated name for HTML tag
     * @return string
     */
    public function buildHtmlName()
    {
        return $this->namespace . '-' . $this->name;
    }


    /**
     * Renders HTML Element
     * @return string
     */
    abstract public function render();

    public function getValue($defaultValue = null) {

        $name = $this->buildHtmlName();

        return $this->getFromSource($name, $defaultValue);
    }
}