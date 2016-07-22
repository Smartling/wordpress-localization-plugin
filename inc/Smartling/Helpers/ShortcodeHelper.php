<?php

namespace Smartling\Helpers;

use Psr\Log\LoggerInterface;
use Smartling\Base\ExportedAPI;
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

    /**
     * Returns a regexp for masked shortcodes
     * @return string
     */
    public static function getShortcodeMaskRegexp()
    {
        return str_replace('%s', self::SMARTLING_SHORTCODE_MASK, '(%s\[[^\]]+\]%s|%s\[[^\]]+\]|\[[^\]]+\]%s)');
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

    private $subNodes = [];

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

    public function addSubNode(\DOMNode $node)
    {
        $this->subNodes[] = $node;
    }

    public function getSubNodes()
    {
        return $this->subNodes;
    }

    public function getTranslatedShortcodes()
    {
        return array_keys($this->shortcodeAttributes);
    }

    public function __construct(LoggerInterface $logger)
    {
        $this->setLogger($logger);
    }

    public function __destruct()
    {
        if (null !== $this->getInitialShortcodeHandlers()) {
            $this->restoreShortcodeHandler();
        }
    }

    /**
     * @return \DOMNode
     */
    private function getNode()
    {
        return $this->getParams()->getNode();
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
                foreach ($cNode->attributes as $attribute => $value) {
                    /**
                     * @var \DOMAttr $value
                     */
                    $tStruct[$attribute] = $value->value;
                }
                $tStruct['value'] = $cNode->nodeValue;
                $this->addShortcodeAttribute($tStruct['shortcode'], $tStruct['name'], $tStruct['value'], $tStruct['hash']);
                $this->getLogger()
                    ->debug(vsprintf('Found translation for shortcode = \'%s\' for attribute = \'%s\'.', [$tStruct['shortcode'],
                                                                                                          $tStruct['name']]));
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

        return $this->getParams();
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
        $this->setParams($params);
        $string = $params->getNode()->nodeValue;
        if ($this->stringHasShortcode($string)) {
            $detectedShortcodes = $this->getShortcodes($string);
            $this->getLogger()->debug(
                vsprintf(
                    'Got string for translation with shortcodes: %s', [implode(';', $detectedShortcodes)]
                )
            );
            $this->replaceHandlerForMining($detectedShortcodes);
            $this->getLogger()->debug(vsprintf('Starting processing shortcodes...', []));
            do_shortcode($string);
            $this->getLogger()->debug(vsprintf('Finished processing shortcodes.', []));
            foreach ($this->getSubNodes() as $node) {
                $this->getLogger()->debug(vsprintf('Adding subNode', []));
                $this->getParams()->getDom()->importNode($node, true);
                $this->getNode()->appendChild($node);
            }
            $this->shortcodeAttributes = [];
            $this->restoreShortcodeHandler();

            return $params;
        } else {
            return $params;
        }
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
            $preparedAttributes = XmlEncoder::prepareSourceArray($attributes);
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
                $node->setAttribute('shortcode', $name);
                $node->setAttribute('hash', md5($value));
                $node->setAttribute('name', $attribute);
                $node->appendChild(new \DOMCdataSection($value));
                $this->addSubNode($node);
            }
        } else {
            $this->getLogger()->debug(vsprintf('No attributes found in shortcode %s.', [$name]));
        }
        $this->maskShortcode($name);
        if (null !== $content) {
            $this->getLogger()->debug(vsprintf('Shortcode %s has content, digging deeper...', [$name]));

            return do_shortcode($content);
        }
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
                if (array_key_exists($attribute, $attr) && reset(array_keys($value)) === md5($attr[$attribute])) {
                    $this->getLogger()->debug(
                        vsprintf(
                            'Validated translation of \'%s\' as \'%s\' with hash=%s for shortcode %s',
                            [
                                $attr[$attribute],
                                reset($value),
                                md5($attr[$attribute]),
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
        }
    }

    /**
     * Checks if given string has shortcodes
     *
     * @param string $string
     *
     * @return bool
     */
    private function stringHasShortcode($string)
    {
        return preg_match(self::WP_SHORTCODE_REGEXP, $string) > 0;
    }

    /**
     * Returns all detected shortcodes
     *
     * @param string $string
     *
     * @return array
     */
    private function getShortcodes($string)
    {
        $matches = [];
        preg_match_all(self::WP_SHORTCODE_REGEXP, $string, $matches);

        return array_key_exists(1, $matches) ? $matches[1] : [];
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

    private function replaceHandlerForMining(array $shortcodeList)
    {
        $this->getLogger()->debug(
            vsprintf(
                'Replacing handler for shortcode mining to %s::%s for shortcodes %s',
                [
                    __CLASS__,
                    'shortcodeHandler',
                    implode(';', $shortcodeList),
                ]
            )
        );
        $this->replaceShortcodeHandler($shortcodeList, 'shortcodeHandler');
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
     * Replaces attributes values for given $shortcodeName in the translation
     *
     * @param string $shortcodeName
     * @param string $values
     */
    private function replaceShortcodeAttributeValue($shortcodeName, $values)
    {
        $node = $this->getNode();
        $initialString = $node->nodeValue;
        $matches = [];
        preg_match_all(
            vsprintf('/%s/', [get_shortcode_regex([$shortcodeName])]),
            $initialString,
            $matches
        );
        $shortcode = $matches[0][0];
        $initialShortcode = $shortcode;
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
            $shortcode = str_replace($originalText, $translation, $shortcode);
        }
        $result = str_replace($initialShortcode, $shortcode, $initialString);
        self::replaceCData($node, $result);
    }

    /**
     * Masks the shortcode by name
     *
     * @param string $shortcodeName
     */
    private function maskShortcode($shortcodeName)
    {
        $this->getLogger()->debug(vsprintf('Preparing to mask shortcode %s.', [$shortcodeName]));
        $node = $this->getNode();
        $initialString = $node->nodeValue;
        $matches = [];
        preg_match_all(vsprintf('/%s/', [get_shortcode_regex([$shortcodeName])]), $initialString, $matches);
        $shortcode = $matches[0][0];
        $masked = vsprintf('%s%s%s', [self::SMARTLING_SHORTCODE_MASK, $matches[0][0], self::SMARTLING_SHORTCODE_MASK]);
        $result = str_replace($shortcode, $masked, $initialString);
        self::replaceCData($node, $result);
    }

    /**
     * Removes smartling masks from the string
     */
    private function unmaskShortcodes()
    {
        $this->getLogger()->debug(vsprintf('Removing masking...', []));
        $node = $this->getNode();
        $string = $node->nodeValue;
        $string = preg_replace(vsprintf('/%s\[/', [self::SMARTLING_SHORTCODE_MASK]), ' [', $string);
        $string = preg_replace(vsprintf('/\]%s/', [self::SMARTLING_SHORTCODE_MASK]), '] ', $string);
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
}