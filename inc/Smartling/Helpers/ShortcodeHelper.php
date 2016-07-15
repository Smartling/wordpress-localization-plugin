<?php

namespace Smartling\Helpers;

use Smartling\Base\ExportedAPI;
use Smartling\Bootstrap;
use Smartling\Helpers\EventParameters\TranslationStringFilterParameters;
use Smartling\WP\WPHookInterface;

/**
 * Class ShortcodeHelper
 * @package Smartling\Helpers
 */
class ShortcodeHelper implements WPHookInterface
{
    /**
     * Regular expression to work with shortcodes, took from do_shortcode() function
     */
    const WP_SHORTCODE_REGEXP = '@\[([^<>&/\[\]\x00-\x20=]++)@';

    const SMARTLING_SHORTCODE_MASK = '##';

    public static function getShortcodeMaskRegexp()
    {
        return str_replace('%s', self::SMARTLING_SHORTCODE_MASK, '(%s\[[^\]]+\]%s|%s\[[^\]]+\]|\[[^\]]+\]%s)');
    }

    /**
     * @var array
     */
    private $initialShortcodeHandlers;

    /**
     * @var array
     */
    private $shortcodeAttributes = [];

    /**
     * @var TranslationStringFilterParameters
     */
    private $params;

    /**
     * @return array
     */
    protected function getInitialShortcodeHandlers()
    {
        return $this->initialShortcodeHandlers;
    }

    /**
     * @param array $initialShortcodeHandlers
     */
    protected function setInitialShortcodeHandlers($initialShortcodeHandlers)
    {
        $this->initialShortcodeHandlers = $initialShortcodeHandlers;
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
     * @return array
     */
    public function getShortcodeAttributes($shortcodeName)
    {
        return array_key_exists($shortcodeName, $this->shortcodeAttributes) ? $this->shortcodeAttributes[$shortcodeName]
            : false;
    }

    /**
     * @param string $shortcodeName
     * @param string $attributeName
     * @param string $translatedString
     * @param string $originalHash
     */
    public function addShortcodeAttribute($shortcodeName, $attributeName, $translatedString, $originalHash)
    {
        $this->shortcodeAttributes[$shortcodeName][$attributeName] = [$originalHash => $translatedString];
    }

    public function getTranslatedShortcodes()
    {
        return array_keys($this->shortcodeAttributes);
    }

    public function __destruct()
    {
        if (null !== $this->getInitialShortcodeHandlers()) {
            $this->restoreShortcodeHandler();
        }
    }

    /**
     * @return array
     */
    private function getShortcodeAssignments()
    {
        global $shortcode_tags;

        return $shortcode_tags;
    }

    private function setShortcodeAssignments(array $assignments)
    {
        global $shortcode_tags;

        $shortcode_tags = $assignments;
    }

    /**
     * Registers wp hook handlers. Invoked by wordpress.
     * @return void
     */
    public function register()
    {
        add_filter(ExportedAPI::FILTER_SMARTLING_TRANSLATION_STRING, [$this, 'processString']);
        add_filter(ExportedAPI::FILTER_SMARTLING_TRANSLATION_STRING_RECEIVED, [$this, 'processTranslation']);
    }

    public function processTranslation(TranslationStringFilterParameters $params)
    {
        $this->setParams($params);
        $node = $this->getParams()->getNode();

        $string = '';
$cDataNode=null;
        foreach ($node->childNodes as $childNode) {
            if ($childNode->nodeType===XML_CDATA_SECTION_NODE)
            {
                $cDataNode=$childNode;
                $string = $childNode->nodeValue;
                break;
            }
        }


        foreach ($node->childNodes as $cNode) {
            /**
             * @var \DOMNode $cNode
             */
            if ($cNode->nodeName === 'shortcodeattribute' && $cNode->hasAttributes()) {
                $translation = [];
                foreach ($cNode->attributes as $attribute => $value) {
                    /**
                     * @var \DOMAttr $value
                     */
                    $translation[$attribute] = $value->value;
                }
                $translation['value'] = $cNode->nodeValue;
                $this->addShortcodeAttribute($translation['shortcode'], $translation['name'], $translation['value'], $translation['hash']);
                //$node->removeChild($cNode);
            }
        }


        while ($node->childNodes->length > 0) {
            $node->removeChild($node->childNodes->item(0));
        }


        //$node->removeChild($cDataNode);
        $node->appendChild(new \DOMCdataSection($string));

        $this->unmaskShortcodes();

        //Bootstrap::DebugPrint($string, true);

        $detectedShortcodes = $this->getTranslatedShortcodes();
        $this->replaceHandlerForApplying($detectedShortcodes);
        do_shortcode($string);
        $this->restoreShortcodeHandler();
        //Bootstrap::DebugPrint($this->getParams()->getNode()->nodeValue, true);

        return $this->getParams();
    }

    private function applyTranslation($shortcode, $translation)
    {

    }

    public function processString(TranslationStringFilterParameters $params)
    {
        $this->setParams($params);
        $string = $params->getNode()->nodeValue;
        if ($this->stringHasShortcode($string)) {
            $detectedShortcodes = $this->getShortcodes($string);
            $this->replaceHandlerForMining($detectedShortcodes);
            do_shortcode($string);
            foreach ($this->getShortcodeAttributes() as $node) {
                $this->getParams()->getNode()->appendChild($node);
            }
            $this->shortcodeAttributes = [];
            $this->restoreShortcodeHandler();

            return $params;
        } else {
            return $params;
        }
    }

    /**
     * replaces all shortcode handlers to extract data
     *
     * @param array       $attributes
     * @param string|null $content
     * @param string      $name
     *
     * @return string
     */
    public function shortcodeHandler($attributes, $content = null, $name)
    {
        // <string> <shortcodeattribute shortcodename="" originalhash="" attributename=""><![CDATA[sdcsd]]></shortcodeattribute> </string>


        $preparedAttributes = XmlEncoder::prepareSourceArray($attributes);


        foreach ($preparedAttributes as $attribute => $value) {
            $node = $this->getParams()->getDom()->createElement('shortcodeattribute');
            $node->setAttribute('shortcode', $name);
            $node->setAttribute('hash', md5($value));
            $node->setAttribute('name', $attribute);
            $node->appendChild(new \DOMCdataSection($value));
            $this->addShortcodeAttribute($node);
        }


        //Bootstrap::DebugPrint($preparedAttributes, true);

        $this->maskShortcode($name);


        if (null !== $content) {
            return do_shortcode($content);
        }
    }

    public function shortcodeApplyerHandler($attributes, $content = null, $name)
    {
        $translations = $this->getShortcodeAttributes($name);

        if (false !== $translations) {
            $translationPairs = [];

            foreach ($translations as $attribute => $value) {
                if (array_key_exists($attribute, $attributes) &&
                    reset(array_keys($value)) === md5($attributes[$attribute])
                ) {
                    $translationPairs[$attributes[$attribute]] = reset($value);


                    //$attributes[$attribute]=reset($value);
                }


            }

            $this->replaceShortcodeAttributeValue($name, $translationPairs);

            if (null !== $content) {
                return do_action($content);
            }


        }


    }

    /**
     * @param string $string
     *
     * @return bool
     */
    private function stringHasShortcode($string)
    {
        return preg_match(self::WP_SHORTCODE_REGEXP, $string) > 0;
    }


    private function getShortcodes($string)
    {
        $matches = [];
        preg_match_all(self::WP_SHORTCODE_REGEXP, $string, $matches);

        return array_key_exists(1, $matches) ? $matches[1] : [];
    }

    private function replaceShortcodeHandler($shortcodes, $callback)
    {
        $activeShortcodeAssignments = $this->getShortcodeAssignments();
        $this->setInitialShortcodeHandlers($activeShortcodeAssignments);

        foreach ($shortcodes as $shortcodeName) {
            $activeShortcodeAssignments[$shortcodeName] = [$this, $callback];
        }
        $this->setShortcodeAssignments($activeShortcodeAssignments);
    }

    private function replaceHandlerForMining(array $shortcodeList)
    {
        $this->replaceShortcodeHandler($shortcodeList, 'shortcodeHandler');
    }

    private function replaceHandlerForApplying(array $shortcodeList)
    {
        $this->replaceShortcodeHandler($shortcodeList, 'shortcodeApplyerHandler');
    }

    private function restoreShortcodeHandler()
    {
        if (null !== $this->getInitialShortcodeHandlers()) {
            $this->setShortcodeAssignments($this->getInitialShortcodeHandlers());
            $this->setInitialShortcodeHandlers(null);
        }
    }

    private function replaceShortcodeAttributeValue($shortcodeName, $values)
    {
        $node = $this->getParams()->getNode();

        $initialString = $node->nodeValue;

        $matches = [];
        preg_match_all(vsprintf('/%s/', [get_shortcode_regex([$shortcodeName])]), $initialString, $matches);
        $shortcode = $matches[0][0];

        $initialShortcode = $shortcode;

        foreach ($values as $originalText => $translation) {
            $shortcode = str_replace($originalText, $translation, $shortcode);
        }

        $result = str_replace($initialShortcode, $shortcode, $initialString);

        $updatedCdata = new \DOMCdataSection($result);

        foreach ($node->childNodes as $cNode) {
            /**
             * @var \DOMNode $cNode
             */
            if (XML_CDATA_SECTION_NODE === $cNode->nodeType) {
                $node->removeChild($cNode);
            }
        }
        $node->appendChild($updatedCdata);
    }


    private function maskShortcode($name)
    {
        $node = $this->getParams()->getNode();

        $initialString = $node->nodeValue;

        $matches = [];
        preg_match_all(vsprintf('/%s/', [get_shortcode_regex([$name])]), $initialString, $matches);
        $shortcode = $matches[0][0];
        $masked = vsprintf('%s%s%s', [self::SMARTLING_SHORTCODE_MASK, $matches[0][0], self::SMARTLING_SHORTCODE_MASK]);

        $result = str_replace($shortcode, $masked, $initialString);

        $updatedCdata = new \DOMCdataSection($result);

        foreach ($node->childNodes as $cNode) {
            /**
             * @var \DOMNode $cNode
             */
            if (XML_CDATA_SECTION_NODE === $cNode->nodeType) {
                $node->removeChild($cNode);
            }
        }
        $node->appendChild($updatedCdata);
    }

    private function unmaskShortcodes()
    {
        $string = $this->getParams()->getNode()->nodeValue;

        $string = preg_replace('/##\[/', '[', $string);
        $string = preg_replace('/\]##/', ']', $string);

        $updatedCdata = new \DOMCdataSection($string);

        $c = $this->getParams()->getNode()->childNodes;

        foreach ($c as $node) {
            /**
             * @var \DOMNode $node
             */
            if (XML_CDATA_SECTION_NODE === $node->nodeType) {
                $this->getParams()->getNode()->removeChild($node);
            }

        }
        $this->getParams()->getNode()->appendChild($updatedCdata);
    }


}