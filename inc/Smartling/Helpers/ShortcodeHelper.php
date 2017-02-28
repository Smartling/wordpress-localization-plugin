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
        $this->getLogger()->debug(vsprintf('Restoring original shortcode handlers', []));
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

        foreach ($node->childNodes as $childNode) {
            if ($childNode->nodeType === XML_CDATA_SECTION_NODE) {
                $string = $childNode->nodeValue;
                break;
            }
        }

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
                $this->addShortcodeAttribute($tStruct['shortcode'], $tStruct['name'], $tStruct['value'],
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
            }
        }

        $this->getLogger()->debug(vsprintf('Rebuilding child nodes...', []));
        while ($node->childNodes->length > 0) {
            $node->removeChild($node->childNodes->item(0));
        }
        $node->appendChild(new \DOMCdataSection($string));


        $this->unmaskShortcodes();
        $detectedShortcodes = $this->getTranslatedShortcodes();
        $this->replaceHandlerForApplying($detectedShortcodes);
        do_shortcode($string);
        $this->restoreShortcodeHandler();

        // pass translated fields through filters

        $this->filterNode();

        return $this->getParams();
    }


    /**
     * Passes attributes and their values through filters
     * Should be called before
     *  -   ExportedAPI::FILTER_SMARTLING_TRANSLATION_STRING
     * and after
     * -    ExportedAPI::FILTER_SMARTLING_TRANSLATION_STRING_RECEIVED
     * Assumes that all data is already loaded.
     */
    public function filterNode()
    {
        $string = $this->getNode()->nodeValue;
        $detectedShortcodes = $this->getRegisteredShortcodes();
        $template = 'Got string for translation looking for shortcodes: \'%s\'';
        $shortcodeList = implode('\'; \'', $detectedShortcodes);
        $this->getLogger()->debug(vsprintf($template, [$shortcodeList]));
        $this->replaceShortcodeHandler($detectedShortcodes, 'filterApplierHandler');
        $this->getLogger()->debug(vsprintf('Starting processing shortcodes...', []));
        $string_m = do_shortcode($string);
        self::replaceCData($this->getNode(), $string_m);
        $this->getLogger()->debug(vsprintf('Finished processing shortcodes.', []));
        $this->restoreShortcodeHandler();
    }

    /**
     * @param array|null  $attributes
     * @param string|null $content
     * @param string      $name
     *
     * @return string
     */
    public function filterApplierHandler($attributes, $content = null, $name)
    {
        if (is_array($attributes)) {
            $template = 'Got shortcode \'%s\' sending for translation %s';
            $this->getLogger()->debug(vsprintf($template, [$name, var_export($attributes, true)]));

            $attributes = $this->filterAttributes($attributes);

            //$preparedAttributes = $fFilter->prepareSourceData($attributes);

            $this->getLogger()->debug(vsprintf('Got filtered shortcodes %s', [var_export($attributes, true)]));
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

        return self::buildShortcode($name, $attributes, $content);
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

    private function replaceHandlerForFiltering(array $shortcodes)
    {
        $this->getLogger()->debug(
            vsprintf(
                'Replacing handler for shortcode attributes filtering to %s::%s for shortcodes %s',
                [
                    __CLASS__,
                    'shortcodeHandler',
                    implode(';', $shortcodes),
                ]
            )
        );
        $this->replaceShortcodeHandler($shortcodes, 'filterApplierHandler');
    }

    private function replaceHandlerForApplying(array $shortcodeList)
    {
        $this->getLogger()->debug(
            vsprintf(
                'Replacing handler for shortcode applying translation to %s::%s for shortcodes %s',
                [
                    __CLASS__,
                    'shortcodeHandler',
                    implode(';', $shortcodeList),
                ]
            )
        );
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


        $this->getLogger()->debug(
            vsprintf(
                'Got string for translation looking for shortcodes: \'%s\'',
                [
                    implode('\'; \'', $detectedShortcodes),
                ]
            )
        );
        $this->replaceHandlerForMining($detectedShortcodes);
        $this->getLogger()->debug(vsprintf('Starting processing shortcodes...', []));
        $string_m = do_shortcode($string);
        self::replaceCData($params->getNode(), $string_m);
        $this->getLogger()->debug(vsprintf('Finished processing shortcodes.', []));
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
        $this->getLogger()->debug(
            vsprintf(
                'Replacing handler for shortcode mining to %s::%s for shortcodes \'%s\'',
                [
                    __CLASS__,
                    'shortcodeHandler',
                    implode('\'; \'', $shortcodeList),
                ]
            )
        );
        $this->replaceShortcodeHandler($shortcodeList, 'shortcodeHandler');
    }

    public function getSubNodes()
    {
        return $this->subNodes;
    }

    /**
     * @param array $attributes
     * @param bool  $removeUnnecessaryFields
     *
     * @return array
     */
    private function filterAttributes(array $attributes, $removeUnnecessaryFields = false)
    {
        $submission = $this->getParams()->getSubmission();
        $fFilter = $this->getFieldsFilter();

        $attributes = $fFilter->passFieldProcessorsFilters($submission, $attributes);

        if (true === $removeUnnecessaryFields) {

            ContentSerializationHelper::prepareFieldProcessorValues($fFilter->getSettingsManager(), $submission);
            $settings = Bootstrap::getContainer()->getParameter('field.processor');

            $attributes = $fFilter->removeFields($attributes, $settings['ignore']);
            $attributes = $fFilter->removeFields($attributes, $settings['copy']['name']);
            $attributes = $fFilter->removeValuesByRegExp($attributes, $settings['copy']['regexp']);
        }

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
    public function shortcodeHandler($attributes, $content = null, $name)
    {
        if (is_array($attributes)) {
            $this->getLogger()->debug(
                vsprintf(
                    'Got shortcode \'%s\' sending for translation %s',
                    [
                        $name,
                        var_export($attributes, true),
                    ]
                )
            );

            $preparedAttributes = $this->filterAttributes($attributes, true);

            //$preparedAttributes = $fFilter->prepareSourceData($attributes);

            $this->getLogger()->debug(
                vsprintf(
                    'Got filtered shortcodes %s',
                    [
                        var_export($preparedAttributes, true),
                    ]
                )
            );
            foreach ($preparedAttributes as $attribute => $value) {
                $node = $this->getParams()->getDom()->createElement('shortcodeattribute');
                $node->setAttributeNode(new \DOMAttr('shortcode', $name));
                $node->setAttributeNode(new \DOMAttr('hash', md5($value)));
                $node->setAttributeNode(new \DOMAttr('name', $attribute));
                $node->appendChild(new \DOMCdataSection($value));
                $this->addSubNode($node);
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
     */
    public function shortcodeApplyerHandler($attr, $content = null, $name)
    {
        $translations = $this->getShortcodeAttributes($name);

        if (false !== $translations) {
            $translationPairs = [];
            foreach ($translations as $attribute => $value) {
                if (array_key_exists($attribute, $attr) &&
                    ArrayHelper::first(array_keys($value)) === md5($attr[$attribute])
                ) {
                    $this->getLogger()->debug(
                        vsprintf(
                            'Validated translation of \'%s\' as \'%s\' with hash=%s for shortcode \'%s\'',
                            [
                                $attr[$attribute],
                                reset($value),
                                md5($attr[$attribute]),
                                $name,
                            ]
                        )
                    );
                    $translationPairs[$attr[$attribute]] = reset($value);
                }
            }
            if (0 < count($translationPairs)) {
                $this->getLogger()->debug(vsprintf('Applying translations...', []));
                $this->replaceShortcodeAttributeValue($name, $translationPairs);
            }

            if (null !== $content) {
                return do_action($content);
            }
        } else {
            $this->getLogger()->debug(vsprintf('No translation found for shortcode %s', [$name]));

            return;
        }
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
     * Replaces attributes values for given $shortcodeName in the translation
     *
     * @param string $shortcodeName
     * @param array  $values
     */
    private function replaceShortcodeAttributeValue($shortcodeName, $values)
    {
        $node = $this->getNode();
        $initialString = $node->nodeValue;
        $matches = [];
        preg_match_all(vsprintf('/%s/', [get_shortcode_regex([$shortcodeName])]), $initialString, $matches);
        if (array_key_exists(0, $matches) && is_array($matches[0])) {
            $shortcodes = &$matches[0];
            /**
             * @var array $shortcodes
             */
            foreach ($shortcodes as $shortcodeString) {
                $initialShortcodeString = $shortcodeString;
                foreach ($values as $originalText => $translation) {
                    $this->getLogger()->debug(
                        vsprintf(
                            'Applying shortcode = \'%s\' attribute translation \'%s\' ==> \'%s\'',
                            [
                                $shortcodeName,
                                $originalText,
                                $translation,
                            ]
                        )
                    );
                    $translatedShortcodeString = str_replace($originalText, $translation, $shortcodeString);
                    $result = str_replace($initialShortcodeString, $translatedShortcodeString, $initialString);
                    if ($result !== $initialString) {
                        self::replaceCData($node, $result);
                        $initialString = $result;
                    }
                }
            }
        }
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

        return $output;
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