<?php

namespace Smartling\Helpers;

use DOMElement;
use DOMNode;
use Smartling\Base\ExportedAPI;
use Smartling\Helpers\EventParameters\TranslationStringFilterParameters;

/**
 * Class ShortcodeHelper
 *
 * @package Smartling\Helpers
 */
class ShortcodeHelper extends SubstringProcessorHelperAbstract
{
    const SMARTLING_SHORTCODE_MASK_OLD = '##';

    const SMARTLING_SHORTCODE_MASK_S = '#sl-start#';
    const SMARTLING_SHORTCODE_MASK_E = '#sl-end#';

    const SHORTCODE_SUBSTRING_NODE_NAME = 'shortcodeattribute';

    /**
     * Returns a regexp for masked shortcodes
     *
     * @return string
     */
    public static function getMaskRegexp()
    {

        $variants = [
            '%s\[\/?[^\]]+\]%e',
        ];

        return str_replace(
            [
                '%s',
                '%e',
            ],
            [
                static::SMARTLING_SHORTCODE_MASK_S,
                static::SMARTLING_SHORTCODE_MASK_E,
            ],
            ('(' . implode('|', $variants) . ')')
        );
    }

    private function resetInternalState()
    {
        $this->blockAttributes = [];
        $this->subNodes        = [];
    }

    /**
     * Restores original shortcode handlers
     */
    public function restoreHandlers()
    {
        if (null !== $this->getInitialHandlers()) {
            $this->setShortcodeAssignments($this->getInitialHandlers());
            $this->setInitialHandlers(null);
        }
    }

    /**
     * Setter for global $shortcode_tags
     *
     * @param array $assignments
     */
    private function setShortcodeAssignments(array $assignments)
    {
        global $shortcode_tags;

        /** @noinspection OnlyWritesOnParameterInspection */
        $shortcode_tags = $assignments;
    }

    /**
     * Registers wp hook handlers. Invoked by wordpress.
     *
     * @return void
     */
    public function register()
    {
        add_filter(ExportedAPI::FILTER_SMARTLING_TRANSLATION_STRING, [$this, 'processString'], 5);
        add_filter(ExportedAPI::FILTER_SMARTLING_TRANSLATION_STRING_RECEIVED, [$this, 'processTranslation'], 99);
    }

    /**
     * @param $string
     * @return bool
     */
    private function hasShortcodes($string)
    {
        $possibleShortcodes = $this->getRegisteredShortcodes();

        global $shortcode_tags;
        $oldTags = $shortcode_tags;

        $shortcode_tags = array_flip($possibleShortcodes);

        foreach ($possibleShortcodes as $possibleShortcode) {
            $result = has_shortcode($string, $possibleShortcode);
            if ($result) {
                $this
                    ->getLogger()
                    ->debug(vsprintf('Detected \'%s\' shortcode in string \'%s\'', [$possibleShortcode, $string]));
                $shortcode_tags = $oldTags;
                return true;
            }
        }
        $shortcode_tags = $oldTags;
        return false;
    }

    /**
     * @param DOMNode $node
     * @return array
     */
    public function extractTranslations(DOMNode $node)
    {
        $translations = [];

        /**
         * Walking back to avoid internal pointer reset
         */
        $index = $node->childNodes->length;
        if (0 === $index) {
            return $translations;
        }
        while ($index) {
            $cNode = $node->childNodes->item(--$index);

            if ($cNode->nodeName === static::SHORTCODE_SUBSTRING_NODE_NAME && $cNode->hasAttributes()) {
                $translation = $this->nodeToArray($cNode);

                $this->addBlockAttribute(
                    $translation['shortcode'],
                    $translation['name'],
                    $translation['value'],
                    $translation['hash']
                );

                $this->getLogger()->debug(
                    vsprintf(
                        'Found translation for shortcode = \'%s\' for attribute = \'%s\'.',
                        [
                            $translation['shortcode'],
                            $translation['name'],
                        ]
                    )
                );
                $this->getLogger()->debug(vsprintf('Removing subnode. Name=\'%s\', Contents: \'%s\'', [
                    static::SHORTCODE_SUBSTRING_NODE_NAME,
                    var_export($translation, true),
                ]));
                $node->removeChild($cNode);
            }
        }
    }

    /**
     * Filter handler
     *
     * @param TranslationStringFilterParameters $params
     *
     * @return TranslationStringFilterParameters
     */
    public function processTranslation(TranslationStringFilterParameters $params)
    {
        $this->resetInternalState();
        $this->setParams($params);
        $node = $this->getNode();
        if ($this->hasShortcodes(static::getCdata($node))) {

            // getting attributes translation
            $this->extractTranslations($node);
            // unmasking string
            $this->unmask();
            $detectedShortcodes = $this->getRegisteredShortcodes();
            $this->replaceHandlerForApplying($detectedShortcodes);
            $string_m = $this->renderString(static::getCdata($node));
            $this->restoreHandlers();
            static::replaceCData($node, $string_m);
        }

        return $this->getParams();
    }

    /**
     * Removes smartling masks from the string
     */
    public function unmask()
    {
        $this->getLogger()->debug(vsprintf('Removing masking...', []));
        $node   = $this->getNode();
        $string = static::getCdata($node);
        $string = preg_replace(vsprintf('/%s\[/', [static::SMARTLING_SHORTCODE_MASK_S]), '[', $string);
        $string = preg_replace(vsprintf('/\]%s/', [static::SMARTLING_SHORTCODE_MASK_E]), ']', $string);
        $string = preg_replace(vsprintf('/\]%s/', [static::SMARTLING_SHORTCODE_MASK_OLD]), ']', $string);
        $string = preg_replace(vsprintf('/%s\[/', [static::SMARTLING_SHORTCODE_MASK_OLD]), '[', $string);
        static::replaceCData($node, $string);
    }

    private function replaceHandlerForApplying(array $shortcodeList)
    {
        $this->replaceShortcodeHandler($shortcodeList, 'shortcodeApplierHandler');
    }

    /**
     * Sets the new handler for a set of shortcodes to process them in a native way
     *
     * @param array  $shortcodes
     * @param string $callback
     * @param null   $obj
     */
    public function replaceShortcodeHandler($shortcodes, $callback, $obj = null)
    {
        if (null === $obj) {
            $obj = $this;
        }
        $activeShortcodeAssignments = $this->getShortcodeAssignments();
        $this->setInitialHandlers($activeShortcodeAssignments);
        foreach ($shortcodes as $shortcodeName) {
            $activeShortcodeAssignments[$shortcodeName] = [$obj, $callback];
        }
        $this->setShortcodeAssignments($activeShortcodeAssignments);
    }

    /**
     * Filter handler
     *
     * @param TranslationStringFilterParameters $params
     *
     * @return TranslationStringFilterParameters
     */
    public function processString(TranslationStringFilterParameters $params)
    {
        $this->resetInternalState();
        $this->setParams($params);
        $string = static::getCdata($params->getNode());
        if (StringHelper::isNullOrEmpty($string)) {
            return $params;
        }
        $detectedShortcodes = $this->getRegisteredShortcodes();
        $this->replaceHandlerForMining($detectedShortcodes);
        $string_m = $this->renderString($string);
        static::replaceCData($params->getNode(), $string_m);
        $this->blockAttributes = [];
        $this->restoreHandlers();
        return $params;
    }

    private function replaceHandlerForMining(array $shortcodeList)
    {
        $handlerName = 'uploadShortcodeHandler';

        $this->replaceShortcodeHandler($shortcodeList, $handlerName);
    }

    /**
     * @param string $name
     * @param array  $attributes
     * @return array
     */
    protected function preUploadFiltering($name, $attributes)
    {
        $preparedAttributes = static::maskAttributes($name, $attributes);
        $this->postReceiveFiltering($preparedAttributes);
        $preparedAttributes = $this->preSendFiltering($preparedAttributes);
        $this->getLogger()->debug(vsprintf('Post filtered attributes (while uploading) %s',
            [var_export($preparedAttributes, true)]));
        $preparedAttributes = static::unmaskAttributes($name, $preparedAttributes);

        return $preparedAttributes;
    }

    /**
     * @param string $shortcodeName
     * @param string $attributeName
     * @param string $value
     * @return DOMElement
     */
    private function createShortcodeAttributeNode($shortcodeName, $attributeName, $value)
    {
        return $this->createDomNode(
            static::SHORTCODE_SUBSTRING_NODE_NAME,
            [
                'shortcode' => $shortcodeName,
                'hash'      => md5($value),
                'name'      => $attributeName,
            ],
            $value);
    }

    /**
     * Handler for shortcodes to prepare strings for translation
     *
     * @param array       $attributes
     * @param string|null $content
     * @param string      $name
     *
     * @return string
     */
    public function uploadShortcodeHandler($attributes, $content = null, $name)
    {
        if (is_array($attributes)) {
            $this->getLogger()->debug(vsprintf('Pre filtered attributes (while uploading) %s',
                [var_export($attributes, true)]));
            $preparedAttributes = $this->preUploadFiltering($name, $attributes);
            if (0 < count($preparedAttributes)) {
                foreach ($preparedAttributes as $attribute => $value) {
                    $this->getParams()->getNode()->appendChild($this->createShortcodeAttributeNode($name, $attribute,
                        $value));
                }
            }
        } else {
            $this->getLogger()->debug(vsprintf('No attributes found in shortcode \'%s\'.', [$name]));
        }
        if (null !== $content) {
            $this->getLogger()->debug(vsprintf('Shortcode \'%s\' has content, digging deeper...', [$name]));
            $content = $this->renderString($content);
        }

        if (!is_array($attributes)) {
            $attributes = [];
        }

        return static::buildMaskedShortcode($name, $attributes, $content);
    }

    /**
     * Generates masked shortcode
     *
     * @param string $name
     * @param array  $attributes
     * @param string $content
     *
     * @return string
     */
    private static function buildMaskedShortcode($name, array $attributes, $content)
    {
        $openString  = static::SMARTLING_SHORTCODE_MASK_S . '[';
        $closeString = ']' . static::SMARTLING_SHORTCODE_MASK_E;

        return static::buildShortcode($name, $attributes, $content, $openString, $closeString);
    }

    /**
     * @param array $attributes
     * @return string
     */
    private static function buildShortcodeAttributes(array $attributes = [])
    {
        $attributesString = '';
        $isInteger        = function ($data) {
            return is_int($data)
                || (string)(int)$data === $data;
        };
        foreach ($attributes as $attributeName => $attributeValue) {
            $attribute = $isInteger($attributeValue)
                ? (int)$attributeValue
                : vsprintf('"%s"', [static::escapeValue($attributeValue)]);
            if (is_string($attributeName)) {
                $attribute = vsprintf('%s=%s', [$attributeName, $attribute]);
            }
            $attributesString .= vsprintf(' %s', [$attribute]);
        }
        return $attributesString;
    }

    private static function escapeValue($data)
    {
        if (function_exists('esc_attr')) {
            return esc_attr($data);
        } else {
            return htmlspecialchars($data);
        }
    }

    /**
     * Since PHP Wordpress shortcode handlers are not sensitive whether shortcode has closing tag or not moving to
     * always-enclosed shortcodes
     * @param string $name
     * @param array  $attributes
     * @param string $content
     * @param string $openString
     * @param string $closeString
     * @return string
     */
    private static function buildShortcode($name, array $attributes, $content, $openString = '[', $closeString = ']')
    {
        return vsprintf('%s%s%s%s%s%s/%s%s', [
            $openString,
            $name,
            static::buildShortcodeAttributes($attributes),
            $closeString,
            $content,
            $openString,
            $name,
            $closeString,
        ]);
    }

    /**
     * @param string $shortcodeName
     * @param array  $attributes
     * @return array
     */
    protected function passPostDownloadFilters($shortcodeName, $attributes)
    {
        if (0 < count($attributes)) {
            $attributes = static::maskAttributes($shortcodeName, $attributes);
            $attributes = $this->postReceiveFiltering($attributes);
            $attributes = static::unmaskAttributes($shortcodeName, $attributes);
        }

        return $attributes;
    }

    /**
     * Applies translation to shortcodes
     *
     * @param string $attr
     * @param string $content
     * @param string $name
     *
     * @return string
     */
    public function shortcodeApplierHandler($attr, $content, $name)
    {
        $attr = is_array($attr) ? $attr : [];

        foreach ($attr as $attributeName => $attributeValue) {
            $attr[$attributeName] = $this->getAttributeTranslation($name, $attributeName, $attributeValue);
        }

        $attr = $this->passPostDownloadFilters($name, $attr);

        if (!StringHelper::isNullOrEmpty($content)) {
            $content = $this->renderString($content);
        }

        return static::buildShortcode($name, $attr, $content);
    }

    /**
     * Returns list of all registered shortcoders in the wordpress
     *
     * @return array
     */
    public function getRegisteredShortcodes()
    {
        $shortcodes = array_keys($this->getShortcodeAssignments());
        try {
            $shortcodes = array_merge($shortcodes, apply_filters(ExportedAPI::FILTER_SMARTLING_INJECT_SHORTCODE, []));
        } catch (\Exception $e) {
            $this->getLogger()->warning(
                vsprintf(
                    'An exception got while applying \'%s\' filter. Ignoring result. Message: %s',
                    [
                        ExportedAPI::FILTER_SMARTLING_INJECT_SHORTCODE,
                        $e->getMessage(),
                    ]
                )
            );
        }
        return array_unique($shortcodes);
    }

    /**
     * Getter for global $shortcode_tags
     *
     * @return array
     */
    private function getShortcodeAssignments()
    {
        global $shortcode_tags;

        return $shortcode_tags;
    }

    /**
     * Wrapper for WP do_shortcode() function. Does nothing while testing
     * @param string $string
     * @return string
     */
    public function renderString($string)
    {
        if (function_exists('do_shortcode')) {
            return do_shortcode($string);
        }
        return $string;
    }
}