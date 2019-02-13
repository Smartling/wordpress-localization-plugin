<?php

namespace Smartling\Helpers;

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
                self::SMARTLING_SHORTCODE_MASK_S,
                self::SMARTLING_SHORTCODE_MASK_E,
            ],
            ('(' . implode('|', $variants) . ')')
        );
    }

    private function resetInternalState()
    {
        $this->blockAttributes = [];
        $this->subNodes = [];
    }

    /**
     * Restores original shortcode handlers
     */
    protected function restoreHandlers()
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

    private function hasShortcodes($string)
    {
        return preg_match('/\[\/?[^\]]+\]/ius', $string);
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

        $string = static::getCdata($node);

        if ($this->hasShortcodes($string)) {
            // getting attributes translation
            foreach ($node->childNodes as $cNode) {
                $this->getLogger()->debug(vsprintf('Looking for translations (subnodes)', []));
                /**
                 * @var \DOMNode $cNode
                 */
                if ($cNode->nodeName === static::SHORTCODE_SUBSTRING_NODE_NAME && $cNode->hasAttributes()) {
                    $tStruct = $this->nodeToArray($cNode);
                    $this->addBlockAttribute($tStruct['shortcode'], $tStruct['name'], $tStruct['value'],
                        $tStruct['hash']);
                    $this->getLogger()->debug(
                        vsprintf(
                            'Found translation for shortcode = \'%s\' for attribute = \'%s\'.',
                            [
                                $tStruct['shortcode'],
                                $tStruct['name'],
                            ]
                        )
                    );
                    $this->getLogger()->debug(vsprintf('Removing subnode. Name=\'%s\', Contents: \'%s\'', [
                        static::SHORTCODE_SUBSTRING_NODE_NAME,
                        var_export($tStruct, true),
                    ]));
                    $node->removeChild($cNode);
                }
            }

            $node->appendChild(new \DOMCdataSection($string));
            // unmasking string
            $this->unmask();
            $string = static::getCdata($this->getNode());
            $detectedShortcodes = $this->getRegisteredShortcodes();
            $this->replaceHandlerForApplying($detectedShortcodes);
            $string_m = do_shortcode($string);

            $this->restoreHandlers();

            self::replaceCData($node, $string_m);
        }


        return $this->getParams();
    }

    /**
     * Removes smartling masks from the string
     */
    protected function unmask()
    {
        $this->getLogger()->debug(vsprintf('Removing masking...', []));
        $node = $this->getNode();
        $string = self::getCdata($node);
        $string = preg_replace(vsprintf('/%s\[/', [self::SMARTLING_SHORTCODE_MASK_S]), '[', $string);
        $string = preg_replace(vsprintf('/\]%s/', [self::SMARTLING_SHORTCODE_MASK_E]), ']', $string);
        $string = preg_replace(vsprintf('/\]%s/', [self::SMARTLING_SHORTCODE_MASK_OLD]), ']', $string);
        $string = preg_replace(vsprintf('/%s\[/', [self::SMARTLING_SHORTCODE_MASK_OLD]), '[', $string);
        self::replaceCData($node, $string);
    }

    public function getTranslatedShortcodes()
    {
        return array_keys($this->blockAttributes);
    }

    private function replaceHandlerForApplying(array $shortcodeList)
    {
        $this->replaceShortcodeHandler($shortcodeList, 'shortcodeApplyerHandler');
    }

    /**
     * Sets the new handler for a set of shortcodes to process them in a native way
     *
     * @param array  $shortcodes
     * @param string $callback
     */
    private function replaceShortcodeHandler($shortcodes, $callback)
    {
        $activeShortcodeAssignments = $this->getShortcodeAssignments();
        $this->setInitialHandlers($activeShortcodeAssignments);
        foreach ($shortcodes as $shortcodeName) {
            $activeShortcodeAssignments[$shortcodeName] = [$this, $callback];
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
        $string = self::getCdata($params->getNode());

        if (StringHelper::isNullOrEmpty($string)) {
            return $params;
        }

        $detectedShortcodes = $this->getRegisteredShortcodes();


        $this->replaceHandlerForMining($detectedShortcodes);
        //$this->getLogger()->debug(vsprintf('Starting processing shortcodes...', []));
        $string_m = do_shortcode($string);
        self::replaceCData($params->getNode(), $string_m);
        //$this->getLogger()->debug(vsprintf('Finished processing shortcodes.', []));
        $this->attachSubnodes();

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
            //passing download filters to create relative structures
            $preparedAttributes = self::maskAttributes($name, $attributes);
            $this->postReceiveFiltering($preparedAttributes);
            $preparedAttributes = $this->preSendFiltering($preparedAttributes);
            $this->getLogger()->debug(vsprintf('Post filtered attributes (while uploading) %s',
                [var_export($preparedAttributes, true)]));
            $preparedAttributes = self::unmaskAttributes($name, $preparedAttributes);
            if (0 < count($preparedAttributes)) {
                foreach ($preparedAttributes as $attribute => $value) {
                    $node = $this->createDomNode(
                        static::SHORTCODE_SUBSTRING_NODE_NAME,
                        [
                            'shortcode' => $name,
                            'hash' => md5($value),
                            'name' => $attribute,
                        ],
                        $value);
                    $this->addSubNode($node);
                }
            }
        } else {
            $this->getLogger()->debug(vsprintf('No attributes found in shortcode \'%s\'.', [$name]));
        }
        if (null !== $content) {
            $this->getLogger()->debug(vsprintf('Shortcode \'%s\' has content, digging deeper...', [$name]));
            $content = do_shortcode($content);
        }

        if (!is_array($attributes)) {
            $attributes = [];
        }

        return self::buildMaskedShortcode($name, $attributes, $content);
    }

    /**
     * Generates masked shortcode
     *
     * @param       $name
     * @param array $attributes
     * @param       $content
     *
     * @return string
     */
    private static function buildMaskedShortcode($name, array $attributes, $content)
    {
        $output = self::SMARTLING_SHORTCODE_MASK_S . '[' . $name;
        foreach ($attributes as $attributeName => $attributeValue) {
            $output .= ' ' . (
                (is_string($attributeName))
                    ? vsprintf('%s="%s"', [$attributeName, esc_attr($attributeValue)])
                    : vsprintf('"%s"', [esc_attr($attributeValue)])
                );
        }
        $output .= ']' . self::SMARTLING_SHORTCODE_MASK_E;
        if (!StringHelper::isNullOrEmpty($content)) {
            $output .= vsprintf(
                '%s%s[/%s]%s',
                [
                    $content,
                    self::SMARTLING_SHORTCODE_MASK_S,
                    $name,
                    self::SMARTLING_SHORTCODE_MASK_E,
                ]
            );
        }

        return $output;
    }

    private static function buildShortcode($name, array $attributes, $content)
    {
        $output = '[' . $name;
        foreach ($attributes as $attributeName => $attributeValue) {
            $output .= ' ' . (
                (is_string($attributeName))
                    ? vsprintf('%s="%s"', [$attributeName, esc_attr($attributeValue)])
                    : vsprintf('"%s"', [esc_attr($attributeValue)])
                );
        }
        $output .= ']';
        if (!StringHelper::isNullOrEmpty($content)) {
            $output .= vsprintf('%s[/%s]', [$content, $name]);
        }

        return $output;
    }

    /**
     * Applies translation to shortcodes
     *
     * @param string      $attr
     * @param string|null $content
     * @param string      $name
     *
     * @return string
     */
    public function shortcodeApplyerHandler($attr, $content = null, $name)
    {
        if (!is_array($attr)) {
            $attr = [];
        }

        // action 0: apply translations
        $translations = $this->getBlockAttributes($name);
        if (0 < count($translations)) {
            foreach ($translations as $attributeName => $translation) {
                if (array_key_exists($attributeName, $attr) &&
                    ArrayHelper::first(array_keys($translation)) === md5($attr[$attributeName])
                ) {
                    $this->getLogger()
                         ->debug(vsprintf('Validated translation of \'%s\' as \'%s\' with hash=%s for shortcode \'%s\'',
                             [
                                 $attr[$attributeName],
                                 reset($translation),
                                 md5($attr[$attributeName]),
                                 $name,
                             ]));
                    $attr[$attributeName] = reset($translation);
                }
            }
        }/* else {
            $this->getLogger()->debug(vsprintf('No translation found for shortcode %s', [$name]));
        }*/
        if (!StringHelper::isNullOrEmpty($content)) {
            $content = do_shortcode($content);
        }
        // action 1: pass through post-translation filters
        if (0 < count($attr)) {
            $attr = self::maskAttributes($name, $attr);
            $attr = $this->postReceiveFiltering($attr);
            $attr = self::unmaskAttributes($name, $attr);
        }

        // action 3: return rebuilded shortcode.
        return self::buildShortcode($name, $attr, $content);
    }


    /**
     * Returns list of all registered shortcoders in the wordpress
     *
     * @return array
     */
    private function getRegisteredShortcodes()
    {
        $output = array_keys($this->getShortcodeAssignments());
        try {
            $extraShortcodes = apply_filters(ExportedAPI::FILTER_SMARTLING_INJECT_SHORTCODE, []);
            if (is_array($extraShortcodes) && 0 < count($extraShortcodes)) {
                foreach ($extraShortcodes as $shortcode) {
                    if (is_string($shortcode)) {
                        $output[] = $shortcode;
                    }
                }
            }
        } catch (\Exception $e) {
            $this->getLogger()->warning(
                vsprintf(
                    'An exception got while applying \'%s\' filter. Ignoring result.',
                    [
                        ExportedAPI::FILTER_SMARTLING_INJECT_SHORTCODE,
                    ]
                )
            );
        }

        $output = array_flip(array_flip($output));
        asort($output);

        return array_values($output);
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
}