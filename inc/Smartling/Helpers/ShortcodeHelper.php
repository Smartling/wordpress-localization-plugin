<?php

namespace Smartling\Helpers;

use Psr\Log\LoggerInterface;
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
    const SMARTLING_SHORTCODE_MASK_OLD = '##';

    const SMARTLING_SHORTCODE_MASK_S = '#sl-start#';
    const SMARTLING_SHORTCODE_MASK_E = '#sl-end#';

    /**
     * Returns a regexp for masked shortcodes
     * @return string
     */
    public static function getShortcodeMaskRegexp()
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

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var array
     */
    private $initialShortcodeHandlers;

    /**
     * @var array
     */
    private $shortcodeAttributes = [];

    /**
     * @var \DOMNode[]
     */
    private $subNodes = [];

    /**
     * @var TranslationStringFilterParameters
     */
    private $params;

    /**
     * @var FieldsFilterHelper
     */
    private $fieldsFilter;

    public function __construct(LoggerInterface $logger)
    {
        $this->setLogger($logger);
    }

    private function resetInternalState()
    {
        $this->shortcodeAttributes = [];
        $this->subNodes = [];
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
     * Restores original shortcode handlers
     */
    private function restoreShortcodeHandler()
    {
        //$this->getLogger()->debug(vsprintf('Restoring original shortcode handlers', []));
        if (null !== $this->getInitialShortcodeHandlers()) {
            $this->setShortcodeAssignments($this->getInitialShortcodeHandlers());
            $this->setInitialShortcodeHandlers(null);
        }
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger($logger)
    {
        $this->logger = $logger;
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


    /**
     * Registers wp hook handlers. Invoked by wordpress.
     * @return void
     */
    public function register()
    {
        add_filter(ExportedAPI::FILTER_SMARTLING_TRANSLATION_STRING, [$this, 'processString']);
        add_filter(ExportedAPI::FILTER_SMARTLING_TRANSLATION_STRING_RECEIVED, [$this, 'processTranslation']);
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

        $string = '';
        // getting string
        foreach ($node->childNodes as $childNode) {
            if ($childNode->nodeType === XML_CDATA_SECTION_NODE) {
                $string = $childNode->nodeValue;
                break;
            }
        }
        // getting attributes translation
        foreach ($node->childNodes as $cNode) {
            $this->getLogger()->debug(vsprintf('Looking for translations (subnodes)', []));
            /**
             * @var \DOMNode $cNode
             */
            if ($cNode->nodeName === 'shortcodeattribute' && $cNode->hasAttributes()) {
                $tStruct = [];
                /** @noinspection ForeachSourceInspection */
                foreach ($cNode->attributes as $attribute => $value) {
                    /**
                     * @var \DOMAttr $value
                     */
                    $tStruct[$attribute] = $value->value;
                }
                $tStruct['value'] = $cNode->nodeValue;
                $this->addShortcodeAttribute($tStruct['shortcode'], $tStruct['name'], $tStruct['value'], $tStruct['hash']);
                $this->getLogger()->debug(
                    vsprintf(
                        'Found translation for shortcode = \'%s\' for attribute = \'%s\'.',
                        [
                            $tStruct['shortcode'],
                            $tStruct['name'],
                        ]
                    )
                );
            }
        }
        // removing translations subnodes
        $this->getLogger()->debug(vsprintf('Rebuilding child nodes...', []));
        while ($node->childNodes->length > 0) {
            $node->removeChild($node->childNodes->item(0));
        }
        $node->appendChild(new \DOMCdataSection($string));
        // unmasking string
        $this->unmaskShortcodes();
        $string = $this->getNode()->nodeValue;
        $detectedShortcodes = $this->getRegisteredShortcodes();
        $this->replaceHandlerForApplying($detectedShortcodes);
        $string_m = do_shortcode($string);

        $this->restoreShortcodeHandler();

        self::replaceCData($node, $string_m);

        return $this->getParams();
    }

    /**
     * @return \DOMNode
     */
    private function getNode()
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
     * @param string $shortcodeName
     * @param string $attributeName
     * @param string $translatedString
     * @param string $originalHash
     */
    public function addShortcodeAttribute($shortcodeName, $attributeName, $translatedString, $originalHash)
    {
        $this->shortcodeAttributes[$shortcodeName][$attributeName] = [$originalHash => $translatedString];
    }

    /**
     * Removes smartling masks from the string
     */
    private function unmaskShortcodes()
    {
        $this->getLogger()->debug(vsprintf('Removing masking...', []));
        $node = $this->getNode();
        $string = $node->nodeValue;
        $string = preg_replace(vsprintf('/%s\[/', [self::SMARTLING_SHORTCODE_MASK_S]), '[', $string);
        $string = preg_replace(vsprintf('/\]%s/', [self::SMARTLING_SHORTCODE_MASK_E]), ']', $string);
        $string = preg_replace(vsprintf('/\]%s/', [self::SMARTLING_SHORTCODE_MASK_OLD]), ']', $string);
        $string = preg_replace(vsprintf('/%s\[/', [self::SMARTLING_SHORTCODE_MASK_OLD]), '[', $string);
        self::replaceCData($node, $string);
    }

    /**
     * Searches and replaces CData section with new one
     *
     * @param \DOMNode $node
     * @param string   $string
     */
    private static function replaceCData(\DOMNode $node, $string)
    {
        $newCdataSection = new \DOMCdataSection($string);
        self::removeChildrenByType($node, XML_CDATA_SECTION_NODE);
        $node->appendChild($newCdataSection);
    }

    /**
     * Removes all child nodes of given type
     *
     * @param \DOMNode $node
     * @param int      $nodeType
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

    public function getTranslatedShortcodes()
    {
        return array_keys($this->shortcodeAttributes);
    }

    private function replaceHandlerForApplying(array $shortcodeList)
    {
        //$this->getLogger()->debug(
        //    vsprintf(
        //        'Replacing handler for shortcode applying translation to %s::%s for shortcodes %s',
        //        [__CLASS__, 'shortcodeHandler', implode(';', $shortcodeList)]
        //    )
        //);
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
        $this->setInitialShortcodeHandlers($activeShortcodeAssignments);
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
        $string = $params->getNode()->nodeValue;
        $detectedShortcodes = $this->getRegisteredShortcodes();


        //$this->getLogger()->debug(
        //    vsprintf(
        //        'Got string for translation looking for shortcodes: \'%s\'',
        //        [
        //            implode('\'; \'', $detectedShortcodes),
        //        ]
        //    )
        //);
        $this->replaceHandlerForMining($detectedShortcodes);
        //$this->getLogger()->debug(vsprintf('Starting processing shortcodes...', []));
        $string_m = do_shortcode($string);
        self::replaceCData($params->getNode(), $string_m);
        //$this->getLogger()->debug(vsprintf('Finished processing shortcodes.', []));
        foreach ($this->getSubNodes() as $node) {
            $this->getLogger()->debug(vsprintf('Adding subNode', []));
            $nodeCopy = $this->getParams()->getDom()->importNode($node, true);
            $this->getNode()->appendChild($nodeCopy);
        }
        $this->shortcodeAttributes = [];
        $this->restoreShortcodeHandler();

        return $params;

    }

    private function replaceHandlerForMining(array $shortcodeList)
    {
        $handlerName = 'uploadShortcodeHandler';
        //$this->getLogger()->debug(
        //    vsprintf(
        //        'Replacing handler for shortcode mining to %s::%s for shortcodes \'%s\'',
        //        [__CLASS__, $handlerName, implode('\'; \'', $shortcodeList)]
        //    )
        //);
        $this->replaceShortcodeHandler($shortcodeList, $handlerName);
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

    private function preSendFiltering(array $attributes)
    {
        $submission = $this->getParams()->getSubmission();
        $fFilter = $this->getFieldsFilter();
        $attributes = $fFilter->passFieldProcessorsBeforeSendFilters($submission, $attributes);
        $attributes = $this->passProfileFilters($attributes);

        return $attributes;
    }

    private function postReceiveFiltering(array $attributes)
    {
        $submission = $this->getParams()->getSubmission();
        $fFilter = $this->getFieldsFilter();
        $attributes = $fFilter->passFieldProcessorsFilters($submission, $attributes);
        return $attributes;
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
            $this->getLogger()
                ->debug(vsprintf('Pre filtered attributes (while uploading) %s', [var_export($attributes, true)]));
            //passing download filters to create relative structures
            $preparedAttributes = self::maskAttributes($name, $attributes);
            $this->postReceiveFiltering($preparedAttributes);
            $preparedAttributes = $this->preSendFiltering($preparedAttributes);
            $this->getLogger()
                ->debug(vsprintf('Post filtered attributes (while uploading) %s', [var_export($preparedAttributes, true)]));
            $preparedAttributes = self::unmaskAttributes($name, $preparedAttributes);
            if (0 < count($preparedAttributes)) {
                foreach ($preparedAttributes as $attribute => $value) {
                    $node = $this->getParams()->getDom()->createElement('shortcodeattribute');
                    $node->setAttributeNode(new \DOMAttr('shortcode', $name));
                    $node->setAttributeNode(new \DOMAttr('hash', md5($value)));
                    $node->setAttributeNode(new \DOMAttr('name', $attribute));
                    $node->appendChild(new \DOMCdataSection($value));
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

    private static function maskAttributes($shortcode, $attributes)
    {
        $output = [];
        foreach ($attributes as $key => $value) {
            $output[$shortcode . '-' . $key] = $value;
        }

        return $output;
    }

    private static function unmaskAttributes($shortcode, $attributes)
    {
        $output = [];
        foreach ($attributes as $key => $value) {
            $output[str_replace($shortcode . '-', '', $key)] = $value;
        }

        return $output;
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

    public function addSubNode(\DOMNode $node)
    {
        $this->subNodes[] = $node;
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
        $translations = $this->getShortcodeAttributes($name);
        if (false !== $translations) {
            foreach ($translations as $attributeName => $translation) {
                if (array_key_exists($attributeName, $attr) &&
                    ArrayHelper::first(array_keys($translation)) === md5($attr[$attributeName])
                ) {
                    $this->getLogger()
                        ->debug(vsprintf('Validated translation of \'%s\' as \'%s\' with hash=%s for shortcode \'%s\'', [$attr[$attributeName],
                                                                                                                         reset($translation),
                                                                                                                         md5($attr[$attributeName]),
                                                                                                                         $name]));
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
     * @param string $shortcodeName
     *
     * @return array|false
     */
    public function getShortcodeAttributes($shortcodeName)
    {
        return array_key_exists($shortcodeName, $this->shortcodeAttributes) ? $this->shortcodeAttributes[$shortcodeName]
            : false;
    }

    /**
     * Returns list of all registered shortcoders in the wordpress
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
     * @return array
     */
    private function getShortcodeAssignments()
    {
        global $shortcode_tags;

        return $shortcode_tags;
    }
}