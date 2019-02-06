<?php

namespace Smartling\Helpers;

use Psr\Log\LoggerInterface;
use Smartling\Bootstrap;
use Smartling\Helpers\EventParameters\TranslationStringFilterParameters;
use Smartling\MonologWrapper\MonologWrapper;
use Smartling\WP\WPHookInterface;

abstract class SubstringProcessorHelperAbstract implements WPHookInterface
{
    /**
     * Returns a regexp for masked shortcodes
     * @return string
     */
    abstract public static function getMaskRegexp();

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var array
     */
    protected $initialHandlers;

    /**
     * @var array
     */
    protected $blockAttributes = [];

    /**
     * @var \DOMNode[]
     */
    protected $subNodes = [];

    /**
     * @var TranslationStringFilterParameters
     */
    private $params;

    /**
     * @var FieldsFilterHelper
     */
    private $fieldsFilter;

    /**
     * SubstringProcessorHelperAbstract constructor.
     */
    public function __construct()
    {
        $this->logger = MonologWrapper::getLogger(get_called_class());
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        if (null !== $this->getInitialHandlers()) {
            $this->restoreHandlers();
        }
    }

    /**
     * @return array
     */
    protected function getInitialHandlers()
    {
        return $this->initialHandlers;
    }

    /**
     * @param array $initialHandlers
     */
    protected function setInitialHandlers($initialHandlers)
    {
        $this->initialHandlers = $initialHandlers;
    }

    /**
     * Restores original shortcode handlers
     */
    protected function restoreHandlers() {

    }

    /**
     * Removes smartling masks from the string
     */
    abstract protected function unmask();

    /**
     * @param string $blockName
     *
     * @return array|false
     */
    public function getBlockAttributes($blockName)
    {
        return array_key_exists($blockName, $this->blockAttributes)
            ? $this->blockAttributes[$blockName]
            : false;
    }

    /**
     * @param \DOMNode $node
     * @return array
     */
    protected function nodeToArray(\DOMNode $node)
    {
        $struct = [];
        /** @noinspection ForeachSourceInspection */
        foreach ($node->attributes as $attributeName => $attributeValue) {
            /**
             * @var \DOMAttr $attributeValue
             */
            $struct[$attributeName] = $attributeValue->value;
        }
        $struct['value'] = $node->nodeValue;
        return $struct;
    }

    /**
     * @param $blockName
     * @param string $attributeName
     * @param string $translatedString
     * @param string $originalHash
     */
    public function addBlockAttribute($blockName, $attributeName, $translatedString, $originalHash)
    {
        $this->blockAttributes[$blockName][$attributeName] = [$originalHash => $translatedString];
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @return FieldsFilterHelper
     */
    public function getFieldsFilter()
    {
        return $this->fieldsFilter;
    }

    /**
     * @param FieldsFilterHelper $fieldsFilter
     */
    public function setFieldsFilter($fieldsFilter)
    {
        $this->fieldsFilter = $fieldsFilter;
    }

    public static function getCdata(\DOMNode $node)
    {
        $result = '';

        foreach ($node->childNodes as $childNode) {
            /**
             * @var \DOMNode $childNode
             */
            if (XML_CDATA_SECTION_NODE === $childNode->nodeType) {
                /**
                 * @var \DOMCdataSection $childNode
                 */
                $result .= $childNode->data;
            }
        }

        return $result;
    }

    /**
     * @return \DOMNode
     */
    protected function getNode()
    {
        return $this->getParams()->getNode();
    }

    /**
     * @return TranslationStringFilterParameters
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * @param TranslationStringFilterParameters $params
     */
    public function setParams($params)
    {
        $this->params = $params;
    }



    /**
     * Searches and replaces CData section with new one
     *
     * @param \DOMNode $node
     * @param string $string
     */
    public static function replaceCData(\DOMNode $node, $string)
    {
        $newCdataSection = new \DOMCdataSection($string);
        self::removeChildrenByType($node, XML_CDATA_SECTION_NODE);
        $node->appendChild($newCdataSection);
    }

    /**
     * Removes all child nodes of given type
     *
     * @param \DOMNode $node
     * @param int $nodeType
     */
    private static function removeChildrenByType(\DOMNode $node, $nodeType)
    {
        foreach ($node->childNodes as $childNode) {
            /**
             * @var \DOMNode $childNode
             */
            if ($nodeType === $childNode->nodeType) {
                $node->removeChild($childNode);
            }
        }
    }

    public function getSubNodes()
    {
        return $this->subNodes;
    }

    /**
     * @param array $attributes
     *
     * @return array
     */
    private function passProfileFilters(array $attributes)
    {
        $submission = $this->getParams()->getSubmission();
        $fFilter = $this->getFieldsFilter();

        ContentSerializationHelper::prepareFieldProcessorValues($fFilter->getSettingsManager(), $submission);
        $settings = Bootstrap::getContainer()->getParameter('field.processor');
        $attributes = $fFilter->removeFields($attributes, $settings['ignore']);
        $attributes = $fFilter->removeFields($attributes, $settings['copy']['name']);

        // adding special pattern to skip:
        $pattern = '^\d+(,\d+)*$';
        $settings['copy']['regexp'][] = $pattern;
        $attributes = $fFilter->removeValuesByRegExp($attributes, $settings['copy']['regexp']);
        $attributes = $fFilter->removeEmptyFields($attributes);

        return $attributes;
    }

    protected function preSendFiltering(array $attributes)
    {
        $submission = $this->getParams()->getSubmission();
        $fFilter = $this->getFieldsFilter();
        $attributes = $fFilter->passFieldProcessorsBeforeSendFilters($submission, $attributes);
        $attributes = $this->passProfileFilters($attributes);

        return $attributes;
    }

    protected function postReceiveFiltering(array $attributes)
    {
        $submission = $this->getParams()->getSubmission();
        $fFilter = $this->getFieldsFilter();
        $attributes = $fFilter->passFieldProcessorsFilters($submission, $attributes);

        return $attributes;
    }

    protected function attachSubnodes(){
        foreach ($this->getSubNodes() as $node) {
            $this->getLogger()->debug('Adding subNode');
            $nodeCopy = $this->getParams()->getDom()->importNode($node, true);
            $this->getNode()->appendChild($nodeCopy);
        }
    }

    protected function createDomNode($nodeName, array $attrs, $value = null) {
        $node = $this->getParams()->getDom()->createElement($nodeName);
        foreach ($attrs as $attrName => $attrValue) {
            $node->setAttributeNode(new \DOMAttr($attrName,$attrValue));
        }
        if (null !== $value) {
            $node->appendChild(new \DOMCdataSection($value));
        }
        return $node;
    }

    protected static function maskAttributes($ns, $attributes)
    {
        $output = [];
        foreach ($attributes as $key => $value) {
            $output[$ns . '-' . $key] = $value;
        }

        return $output;
    }

    protected static function unmaskAttributes($ns, $attributes)
    {
        $output = [];
        foreach ($attributes as $key => $value) {
            $output[str_replace($ns . '-', '', $key)] = $value;
        }

        return $output;
    }

    public function addSubNode(\DOMNode $node)
    {
        $this->subNodes[] = $node;
    }
}
