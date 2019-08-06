<?php

namespace Smartling\Helpers;

use Smartling\Base\ExportedAPI;
use Smartling\Exception\ShortcodeDetectedException;
use Smartling\Helpers\EventParameters\TranslationStringFilterParameters;
use Thunder\Shortcode\HandlerContainer\HandlerContainer;
use Thunder\Shortcode\Parser\RegularParser;
use Thunder\Shortcode\Processor\Processor;
use Thunder\Shortcode\Shortcode\Shortcode;
use Thunder\Shortcode\Shortcode\ShortcodeInterface;

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
     * @var Processor
     */
    private $processor;

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
        $this->processor       = null;
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
        $handlers           = new HandlerContainer();
        foreach ($possibleShortcodes as $possibleShortcode) {
            $handlers->add($possibleShortcode, function (ShortcodeInterface $shortcode) {
                throw new ShortcodeDetectedException('Shortcode detected');
            });
        }

        try {
            (new Processor(new RegularParser(), $handlers))->process($string);
        } catch (ShortcodeDetectedException $e) {
            return true;
        } catch (\Exception $e) {
            $this->getLogger()->error(vsprintf('Shortcode detection failed. Message: %s', [$e->getMessage()]));
        }

        return false;
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

            // unmasking string
            $this->unmask();
            $string             = static::getCdata($this->getNode());
            $detectedShortcodes = $this->getRegisteredShortcodes();
            $this->replaceHandlerForApplying($detectedShortcodes);

            $string_m = $this->processor->process($string);

            static::replaceCData($node, $string_m);
        }

        return $this->getParams();
    }

    /**
     * Removes smartling masks from the string
     */
    protected function unmask()
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

    public function getTranslatedShortcodes()
    {
        return array_keys($this->blockAttributes);
    }

    private function replaceHandlerForApplying(array $shortcodeList)
    {
        $this->setUpShortcodeProcessor($shortcodeList, 'shortcodeApplyerHandler');
    }

    /**
     * Sets the new handler for a set of shortcodes to process them in a native way
     *
     * @param array  $shortcodes
     * @param string $callback
     */
    private function setUpShortcodeProcessor($shortcodes, $callback)
    {
        $handlers = new HandlerContainer();

        foreach ($shortcodes as $shortcode) {
            $handlers->add($shortcode, [$this, $callback]);
        }
        $processor       = new Processor(new RegularParser(), $handlers);
        $this->processor = $processor;
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
        $this->getLogger()->debug(var_export($detectedShortcodes, true));
        $this->setUpShortcodeProcessorForUpload($detectedShortcodes);
        $string_m = $this->processor->process($string);
        static::replaceCData($params->getNode(), $string_m);
        $this->attachSubnodes();
        return $params;
    }

    private function setUpShortcodeProcessorForUpload(array $shortcodeList)
    {
        $handlerName = 'uploadShortcodeHandler';

        $this->setUpShortcodeProcessor($shortcodeList, $handlerName);
    }

    public function uploadShortcodeHandler(ShortcodeInterface $shortcode)
    {
        if (is_array($shortcode->getParameters())) {
            $this->getLogger()->debug(vsprintf('Pre filtered attributes (while uploading) %s',
                [var_export($shortcode->getParameters(), true)]));
            //passing download filters to create relative structures
            $preparedAttributes = static::maskAttributes($shortcode->getName(), $shortcode->getParameters());
            $this->postReceiveFiltering($preparedAttributes);
            $preparedAttributes = $this->preSendFiltering($preparedAttributes);
            $this->getLogger()->debug(vsprintf('Post filtered attributes (while uploading) %s',
                [var_export($preparedAttributes, true)]));
            $preparedAttributes = static::unmaskAttributes($shortcode->getName(), $preparedAttributes);
            if (0 < count($preparedAttributes)) {
                foreach ($preparedAttributes as $attribute => $value) {
                    $node = $this->createDomNode(
                        static::SHORTCODE_SUBSTRING_NODE_NAME,
                        [
                            'shortcode' => $shortcode->getName(),
                            'hash'      => md5($value),
                            'name'      => $attribute,
                        ],
                        $value);
                    $this->addSubNode($node);
                }
            }
        } else {
            $this->getLogger()->debug(vsprintf('No attributes found in shortcode \'%s\'.', [$shortcode->getName()]));
        }

        $content = $shortcode->getContent();

        if (!StringHelper::isNullOrEmpty($content)) {
            $content = $this->processor->withRecursionDepth(1)->process($content);
        }

        $shortcode = new Shortcode($shortcode->getName(), $shortcode->getParameters(), $content,
            $shortcode->getBbCode());
        return static::buildMaskedShortcode($shortcode);
    }

    /**
     * @param ShortcodeInterface $shortcode
     * @return string
     */
    private static function buildAttributesPart(ShortcodeInterface $shortcode)
    {
        $attributes = '';

        foreach ($shortcode->getParameters() as $attributeName => $attributeValue) {
            $attributes .= ' ' . (
                (is_string($attributeName))
                    ? vsprintf('%s="%s"', [$attributeName, esc_attr($attributeValue)])
                    : vsprintf('"%s"', [esc_attr($attributeValue)])
                );
        }

        return $attributes;
    }

    /**
     * @param ShortcodeInterface $shortcode
     * @return string
     */
    private static function buildMaskedShortcode(ShortcodeInterface $shortcode)
    {
        $openString  = vsprintf('%s[', [static::SMARTLING_SHORTCODE_MASK_S]);
        $closeString = vsprintf(']%s', [static::SMARTLING_SHORTCODE_MASK_E]);
        return static::buildShortcode($shortcode, $openString, $closeString);
    }

    /**
     * Renders shortcode
     * @param ShortcodeInterface $shortcode
     * @param string             $openString
     * @param string             $closeString
     * @return string
     */
    private static function buildShortcode(ShortcodeInterface $shortcode, $openString = '[', $closeString = ']')
    {
        if (static::isBlock($shortcode)) {
            return vsprintf('%s%s%s%s%s%s/%s%s', [
                $openString,
                $shortcode->getName(),
                static::buildAttributesPart($shortcode),
                $closeString,
                ($shortcode->getContent() ?: ''),
                $openString,
                $shortcode->getName(),
                $closeString,
            ]);
        } else {
            return vsprintf('%s%s%s%s', [
                $openString,
                $shortcode->getName(),
                static::buildAttributesPart($shortcode),
                $closeString,
            ]);
        }
    }

    /**
     * Applies translation to shortcode and renders it back
     * @param ShortcodeInterface $shortcode
     * @return string
     */
    public function shortcodeApplyerHandler(ShortcodeInterface $shortcode)
    {
        $processedAttributes = $shortcode->getParameters();

        $translations = $this->getBlockAttributes($shortcode->getName());
        if (0 < count($translations)) {
            foreach ($translations as $attributeName => $translation) {
                if (array_key_exists($attributeName, $shortcode->getParameters()) &&
                    ArrayHelper::first(array_keys($translation)) === md5($shortcode->getParameters()[$attributeName])
                ) {
                    $this->getLogger()
                         ->debug(vsprintf('Validated translation of \'%s\' as \'%s\' with hash=%s for shortcode \'%s\'',
                             [
                                 $shortcode->getParameters()[$attributeName],
                                 ArrayHelper::first($translation),
                                 md5($shortcode->getParameters()[$attributeName]),
                                 $shortcode->getName(),
                             ]));
                    $processedAttributes[$attributeName] = ArrayHelper::first($translation);
                }
            }
        }

        if (0 < count($processedAttributes)) {
            $processedAttributes = static::maskAttributes($shortcode->getName(), $processedAttributes);
            $processedAttributes = $this->postReceiveFiltering($processedAttributes);
            $processedAttributes = static::unmaskAttributes($shortcode->getName(), $processedAttributes);
        }

        $newShortcode = new Shortcode(
            $shortcode->getName(),
            $processedAttributes,
            $shortcode->getContent(),
            $shortcode->getBbCode()
        );

        return static::buildShortcode($newShortcode);
    }

    /**
     * @return array
     */
    private function getRegisteredShortcodes()
    {
        $output = $this->getWpShortcodeTags();
        try {
            $extraShortcodes = $this->getVirtualShortcodeTags();
            $output          = array_unique(array_merge($output, $extraShortcodes));
        } catch (\Exception $e) {
            $msg = vsprintf('An exception got while applying \'%s\' filter. Ignoring result.',
                [ExportedAPI::FILTER_SMARTLING_INJECT_SHORTCODE]);
            $this->getLogger()->warning($msg);
        }

        return $output;
    }

    /**
     * Checks whether $shortcode is a block by looking for closing tag in it.
     *
     * @param ShortcodeInterface $shortcode
     * @return bool
     */
    private static function isBlock(ShortcodeInterface $shortcode)
    {
        return null === $shortcode->getContent();
    }

    /**
     * @return array
     */
    private function getVirtualShortcodeTags()
    {
        return apply_filters(ExportedAPI::FILTER_SMARTLING_INJECT_SHORTCODE, []);
    }

    /**
     * @return array
     */
    private function getWpShortcodeTags()
    {
        global $shortcode_tags;

        return array_keys($shortcode_tags);
    }
}