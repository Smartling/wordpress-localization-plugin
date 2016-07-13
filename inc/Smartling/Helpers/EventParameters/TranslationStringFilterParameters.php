<?php

namespace Smartling\Helpers\EventParameters;

/**
 * Class TranslationStringFilterParameters
 * @package Smartling\Helpers\EventParameters
 */
class TranslationStringFilterParameters
{
    /**
     * @var array
     */
    private $filterSettings;

    /**
     * @var \DOMDocument
     */
    private $dom;

    /**
     * @var \DOMNode
     */
    private $node;

    /**
     * @return array
     */
    public function getFilterSettings()
    {
        return $this->filterSettings;
    }

    /**
     * @param array $filterSettings
     */
    public function setFilterSettings($filterSettings)
    {
        $this->filterSettings = $filterSettings;
    }

    /**
     * @return \DOMDocument
     */
    public function getDom()
    {
        return $this->dom;
    }

    /**
     * @param \DOMDocument $dom
     */
    public function setDom($dom)
    {
        $this->dom = $dom;
    }

    /**
     * @return \DOMNode
     */
    public function getNode()
    {
        return $this->node;
    }

    /**
     * @param \DOMNode $node
     */
    public function setNode($node)
    {
        $this->node = $node;
    }
}