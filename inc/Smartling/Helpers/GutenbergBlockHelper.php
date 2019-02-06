<?php

namespace Smartling\Helpers;

use Smartling\Base\ExportedAPI;
use Smartling\Bootstrap;
use Smartling\Exception\SmartlingGutenbergNotFoundException;
use Smartling\Helpers\EventParameters\TranslationStringFilterParameters;

/**
 * Class SubstringProcessorHelperAbstract
 * @package Smartling\Helpers
 */
class GutenbergBlockHelper extends SubstringProcessorHelperAbstract
{
    const SMARTLING_GUTENBERG_MASK_S = '#sl-gutenberg-start#';
    const SMARTLING_GUTENBERG_MASK_E = '#sl-gutenberg-end#';

    const SMARTLING_SUBSTRING_NODE_NAME = 'gutenbergblockattribute';

    /**
     * Returns a regexp for masked shortcodes
     * @return string
     */
    public static function getMaskRegexp()
    {

        $variants = [
            '%s\<!--\s\/?[^(-->)]+-->%e',
        ];

        return str_replace(
            [
                '%s',
                '%e',
            ],
            [
                self::SMARTLING_GUTENBERG_MASK_S,
                self::SMARTLING_GUTENBERG_MASK_E,
            ],
            ('(' . implode('|', $variants) . ')')
        );
    }

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

    private function renderBlock(array $block, $masked = false)
    {
        /**
         * render content out of blocks
         */
        if (null === $block['blockName']) {
            return $block['innerContent'];
        }

        $indexPointer = 0;
        $content = '';

        foreach ($block['innerContent'] as $chunk) {
            $content .= is_string($chunk)
                ? $chunk
                : $this->renderBlock($block['innerBlocks'][$indexPointer++], $masked);
        }

        $renderBlockTag = function ($blockName, array $attributes, $closingTag = false, $masked = false) {

            return vsprintf('%s<!-- %swp:%s %s -->%s', [
                (true === $masked ? self::SMARTLING_GUTENBERG_MASK_S : ''),
                (true === $closingTag ? '/' : ''),
                $blockName,
                json_encode($attributes),
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
     * @param array $block
     */
    public function processBlockAttributes(array $block)
    {
        if (null !== $block['blockName'] && 0 < count($block['attrs'])) {
            $logMsg = vsprintf(
                'Pre filtered block \'%s\' attributes \'%s\'',
                [
                    $block['blockName'],
                    var_export($block['attrs'], true)
                ]
            );
            $this->getLogger()->debug($logMsg);
            $preparedAttributes = self::maskAttributes($block['blockName'], $block['attrs']);
            $this->postReceiveFiltering($preparedAttributes);
            $preparedAttributes = $this->preSendFiltering($preparedAttributes);
            $logMsg = vsprintf(
                'Post filtered block \'%s\' attributes \'%s\'',
                [
                    $block['blockName'],
                    var_export($preparedAttributes, true)
                ]
            );
            $this->getLogger()->debug($logMsg);

            $preparedAttributes = self::unmaskAttributes($block['blockName'], $preparedAttributes);
            if (0 < count($preparedAttributes)) {
                foreach ($preparedAttributes as $attribute => $value) {
                    if(is_array($value)) {
                        Bootstrap::DebugPrint($block, true);
                    }

                    $node = $this->createDomNode(
                        static::SMARTLING_SUBSTRING_NODE_NAME,
                        [
                            'block' => $block['blockName'],
                            'hash' => md5($value),
                            'name' => $attribute,
                        ],
                        $value
                    );

                    $this->addSubNode($node);
                }
            }
        } else {
            $this->getLogger()->debug(vsprintf('No attributes found in block \'%s\'.', [$block['blockName']]));
        }
    }

    private function hasBlocks($string)
    {
        return (false !== strpos($string, '<!-- wp:'));
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
        $reRenderedString = '';
        foreach ($originalBlocks as $id => $block) {
            $reRenderedString .= $this->renderBlock($block, true);
            $this->processBlockAttributes($block);
        }
        self::replaceCData($params->getNode(), $reRenderedString);
        $this->attachSubnodes();

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
