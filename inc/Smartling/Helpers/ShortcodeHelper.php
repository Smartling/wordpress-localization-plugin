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
    private const SMARTLING_SHORTCODE_MASK_OLD = '##';
    private const SHORTCODE_SUBSTRING_NODE_NAME = 'shortcodeattribute';

    /**
     * Returns a regexp for masked shortcodes
     */
    public static function getMaskRegexp(): string
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
                PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_START,
                PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_END,
            ],
            ('(' . implode('|', $variants) . ')')
        );
    }

    private function resetInternalState(): void
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

        $shortcode_tags = $assignments;
    }

    /**
     * Registers wp hook handlers. Invoked by wordpress.
     */
    public function register(): void
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

    public function extractTranslations(DOMNode $node): array
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

            if ($cNode->nodeName === self::SHORTCODE_SUBSTRING_NODE_NAME && $cNode->hasAttributes()) {
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
                    self::SHORTCODE_SUBSTRING_NODE_NAME,
                    var_export($translation, true),
                ]));
                $node->removeChild($cNode);
            }
        }

        return $translations;
    }

    public function processTranslation(TranslationStringFilterParameters $params): TranslationStringFilterParameters
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
        $string = preg_replace(vsprintf('/%s\[/', [PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_START]), '[', $string);
        $string = preg_replace(vsprintf('/\]%s/', [PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_END]), ']', $string);
        $string = preg_replace(vsprintf('/\]%s/', [self::SMARTLING_SHORTCODE_MASK_OLD]), ']', $string);
        $string = preg_replace(vsprintf('/%s\[/', [self::SMARTLING_SHORTCODE_MASK_OLD]), '[', $string);
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

    public function processString(TranslationStringFilterParameters $params): TranslationStringFilterParameters
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
            self::SHORTCODE_SUBSTRING_NODE_NAME,
            [
                'shortcode' => $shortcodeName,
                'hash'      => md5($value),
                'name'      => $attributeName,
            ],
            $value);
    }

    /**
     * Handler for shortcodes to prepare strings for translation
     * @see https://codex.wordpress.org/Shortcode_API for parameters list
     *
     * @param array|string $attributes
     */
    public function uploadShortcodeHandler($attributes, ?string $content, string $name): string
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
            $attributes = [];
        }
        if (null !== $content) {
            $this->getLogger()->debug(vsprintf('Shortcode \'%s\' has content, digging deeper...', [$name]));
            $content = $this->renderString($content);
        }

        return static::buildMaskedShortcode($name, $attributes, $content);
    }

    /**
     * Generates masked shortcode
     */
    private static function buildMaskedShortcode(string $name, array $attributes, string $content): string
    {
        $openString  = PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_START . '[';
        $closeString = ']' . PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_END;

        return static::buildShortcode($name, $attributes, $content, $openString, $closeString);
    }

    private static function buildShortcodeAttributes(array $attributes = []): string
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
        }

        return htmlspecialchars($data);
    }

    /**
     * Since PHP Wordpress shortcode handlers are not sensitive whether shortcode has closing tag or not moving to
     * always-enclosed shortcodes
     */
    private static function buildShortcode(string $name, array $attributes, ?string $content, string $openString = '[', string $closeString = ']'): string
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
     */
    public function shortcodeApplierHandler($attr, ?string $content, string $name): string
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

    public function getRegisteredShortcodes(): array
    {
        $shortcodes = array_keys($this->getShortcodeAssignments());
        try {
            $injectedShortcodes = apply_filters(ExportedAPI::FILTER_SMARTLING_INJECT_SHORTCODE, []);
            if (!is_array($injectedShortcodes)) {
                $this->getLogger()->critical('Injected shortcodes not an array after filter ' . ExportedAPI::FILTER_SMARTLING_INJECT_SHORTCODE . '. This is most likely due to an error outside of the plugins code.');
            }
            $shortcodes = array_merge($shortcodes, $injectedShortcodes);
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
     */
    private function getShortcodeAssignments(): array
    {
        global $shortcode_tags;

        return $shortcode_tags;
    }

    /**
     * Wrapper for WP do_shortcode() function. Does nothing while testing
     * @param string $string
     * @return string
     */
    public function renderString($string): string
    {
        if (function_exists('do_shortcode')) {
            return do_shortcode($string);
        }
        return $string;
    }
}