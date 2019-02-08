<?php

namespace Smartling\Helpers;

use Smartling\Base\ExportedAPI;
use Smartling\Exception\SmartlingGutenbergNotFoundException;
use Smartling\Helpers\EventParameters\TranslationStringFilterParameters;

/**
 * Class SubstringProcessorHelperAbstract
 * @package Smartling\Helpers
 */
class GutenbergBlockHelper extends SubstringProcessorHelperAbstract
{
    /**
     * Registers wp hook handlers. Invoked by wordpress.
     * @return void
     */
    public function register()
    {
        try {
            $this->loadBlockClass();
            add_filter(ExportedAPI::FILTER_SMARTLING_TRANSLATION_STRING, [$this, 'processString']);
            add_filter(ExportedAPI::FILTER_SMARTLING_TRANSLATION_STRING_RECEIVED, [$this, 'processTranslation']);
        } catch (SmartlingGutenbergNotFoundException $e) {
            $this->getLogger()->notice($e->getMessage());
        }
    }

    private function renderBlock(array $block)
    {
        /**
         * render content out of blocks
         */
        if (null === $block['blockName']) {
            return implode('', $block['innerContent']);
        }

        $indexPointer = 0;
        $content = '';

        foreach ($block['innerContent'] as $chunk) {
            $content .= is_string($chunk)
                ? $chunk
                : $this->renderBlock($block['innerBlocks'][$indexPointer++], $masked);
        }

        $renderBlockTag = function ($blockName, array $attributes, $closingTag = false, $masked = false) {

            return vsprintf('%s<!-- %swp:%s%s -->%s', [
                (true === $masked ? self::SMARTLING_GUTENBERG_MASK_S : ''),
                (true === $closingTag ? '/' : ''),
                $blockName,
                (0 < count($attributes) ? ' ' . json_encode($attributes) : ''),
                (true === $masked ? self::SMARTLING_GUTENBERG_MASK_E : ''),
            ]);
        };

        $block = vsprintf('%s%s%s', [
            $renderBlockTag($block['blockName'], $block['attrs'], false, $masked),
            $content,
            $renderBlockTag($block['blockName'], $block['attrs'], true, $masked),
        ]);

        return $block;

    }

    /**
     * @param $blockName
     * @param array $flatAttributes
     * @return array
     */
    public function processAttributes($blockName, array $flatAttributes)
    {
        $attributes = [];
        if (null !== $blockName && 0 < count($flatAttributes)) {
            $logMsg = vsprintf(
                'Pre filtered block \'%s\' attributes \'%s\'', [$blockName, var_export($flatAttributes, true)]
            );
            $this->getLogger()->debug($logMsg);
            $prepAttributes = self::maskAttributes($blockName, $flatAttributes);
            $this->postReceiveFiltering($prepAttributes);
            $prepAttributes = $this->preSendFiltering($prepAttributes);
            $logMsg = vsprintf(
                'Post filtered block \'%s\' attributes \'%s\'', [$blockName, var_export($prepAttributes, true)]
            );
            $this->getLogger()->debug($logMsg);
            $attributes = self::unmaskAttributes($blockName, $prepAttributes);
        } else {
            $this->getLogger()->debug(vsprintf('No attributes found in block \'%s\'.', [$blockName]));
        }
        return $attributes;
    }

    private function hasBlocks($string)
    {
        return (false !== strpos($string, '<!-- wp:'));
    }

    private function placeBlock(array $block)
    {
        $indexPointer = 0;

        $node = self::createDomNode(
            'gutenbergBlock',
            [
                'blockName' => $block['blockName'],
                'originalAttributes' => base64_encode(serialize($block['attrs']))
            ],
            ''
        );

        foreach ($block['innerContent'] as $chunk) {
            $part = null;
            if (is_string($chunk)) {
                $part = self::createDomNode('contentChunk', ['hash' => md5($chunk)], $chunk);
            } else {
                $part = $this->placeBlock($block['innerBlocks'][$indexPointer++]);
            }
            $node->appendChild($part);
        }

        $flatAttributes = $this->getFieldsFilter()->flatternArray($block['attrs']);

        $filteredAttributes = $this->processAttributes($block['blockName'], $flatAttributes);

        foreach ($filteredAttributes as $attrName => $attrValue) {
            $arrtNode = self::createDomNode('blockAttribute', [
                'name' => $attrName,
                'hash' => md5($attrValue),
            ], $attrValue);
            $node->appendChild($arrtNode);
        }

        return $node;
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
        $this->subNodes = [];
        $this->setParams($params);
        $string = self::getCdata($params->getNode());
        if (!$this->hasBlocks($string)) {
            return $params;
        }
        $originalBlocks = gutenberg_parse_blocks($string);

        foreach ($originalBlocks as $block) {
            $node = $this->placeBlock($block);
            $params->getNode()->appendChild($node);
        }
        self::replaceCData($params->getNode(), '');

        return $params;
    }

    /**
     * @param \DOMNode $stringNode
     */
    protected function extractAttributeTranslations(\DOMNode $stringNode)
    {
        foreach ($stringNode->childNodes as $cNode) {
            $this->getLogger()->debug(vsprintf('Looking for translations (subnodes)', []));
            /**
             * @var \DOMNode $cNode
             */
            if ($cNode->nodeName === static::SMARTLING_SUBSTRING_NODE_NAME && $cNode->hasAttributes()) {
                $tStruct = $this->nodeToArray($cNode);

                $this->addBlockAttribute($tStruct['block'], $tStruct['name'], $tStruct['value'], $tStruct['hash']);
                $this->getLogger()->debug(
                    vsprintf(
                        'Found translation for block = \'%s\' for attribute = \'%s\'.',
                        [
                            $tStruct['block'],
                            $tStruct['name'],
                        ]
                    )
                );
                $this->getLogger()->debug(vsprintf('Removing subnode. Name=\'%s\', Contents: \'%s\'', [
                    static::SMARTLING_SUBSTRING_NODE_NAME,
                    var_export($tStruct, true)
                ]));
                $stringNode->removeChild($cNode);
            }
        }
    }

    private function processAttributesTranslations(array $block)
    {
        if (null === $block['blockName']) {
            return $block;
        }

        $attr = &$block['attrs'];

        if (!is_array($attr)) {
            $attr = [];
        }

        // action 0: apply translations
        $translations = $this->getBlockAttributes($block['blockName']);
        if (false !== $translations) {
            foreach ($translations as $attributeName => $translation) {
                if (array_key_exists($attributeName,
                        $attr) && ArrayHelper::first(array_keys($translation)) === md5($attr[$attributeName])) {
                    $this->getLogger()
                        ->debug(vsprintf('Validated translation of \'%s\' as \'%s\' with hash=%s for block \'%s\'',
                            [
                                $attr[$attributeName],
                                reset($translation),
                                md5($attr[$attributeName]),
                                $block['blockName']
                            ]));
                    $attr[$attributeName] = reset($translation);
                }
            }
        }

        if (0 < count($attr)) {
            $attr = self::maskAttributes($block['blockName'], $attr);
            $attr = $this->postReceiveFiltering($attr);
            $attr = self::unmaskAttributes($block['blockName'], $attr);
        }

        return $block;
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
        $this->extractAttributeTranslations($node);

        $string = static::getCdata($node);

        // getting attributes translation
        $this->unmask();

        $originalBlocks = gutenberg_parse_blocks($string);
        $reRenderedString = '';
        foreach ($originalBlocks as $id => $block) {
            $this->processAttributesTranslations($block);
            $reRenderedString .= $this->renderBlock($block, false);
        }
        self::replaceCData($params->getNode(), $reRenderedString);

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
        $string = preg_replace(vsprintf('/%s\<!--/', [self::SMARTLING_GUTENBERG_MASK_S]), '<!--', $string);
        $string = preg_replace(vsprintf('/\-->%s/', [self::SMARTLING_GUTENBERG_MASK_E]), '-->', $string);
        self::replaceCData($node, $string);
    }

    /**
     * @throws SmartlingGutenbergNotFoundException
     */
    private function loadBlockClass()
    {
        $paths = [
            vsprintf('%swp-includes/blocks.php', [ABSPATH]),
            vsprintf('%swp-content/plugins/gutenberg/lib/blocks.php', [ABSPATH])
        ];

        foreach ($paths as $path) {
            //$this->getLogger()->debug(vsprintf('Trying to get block class from file: %s', [$path]));
            if (file_exists($path) && is_readable($path)) {
                /** @noinspection PhpIncludeInspection */
                require_once $path;
                return;
            }
        }

        throw new SmartlingGutenbergNotFoundException("Gutenberg class not found. Disabling GutenbergSupport.");
    }
}
