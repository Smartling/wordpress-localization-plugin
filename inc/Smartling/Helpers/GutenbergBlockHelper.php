<?php

namespace Smartling\Helpers;

use Smartling\Base\ExportedAPI;
use Smartling\Exception\SmartlingConfigException;
use Smartling\Exception\SmartlingGutenbergNotFoundException;
use Smartling\Exception\SmartlingGutenbergParserNotFoundException;
use Smartling\Helpers\EventParameters\TranslationStringFilterParameters;

class GutenbergBlockHelper extends SubstringProcessorHelperAbstract
{
    const BLOCK_NODE_NAME = 'gutenbergBlock';
    const CHUNK_NODE_NAME = 'contentChunk';
    const ATTRIBUTE_NODE_NAME = 'blockAttribute';

    /**
     * @param array $definitions
     * @return array
     */
    public function registerFilters(array $definitions)
    {
        $copyList = [
            '^type$',
            '^providerNameSlug$',
            '^align$',
            '^className$',
        ];

        foreach ($copyList as $fieldName) {
            $definitions[] = [
                'pattern' => $fieldName,
                'action' => 'copy',
            ];
        }

        return $definitions;
    }

    /**
     * Registers wp hook handlers. Invoked by wordpress.
     *
     * @return void
     * @throws SmartlingConfigException
     */
    public function register()
    {
        $handlers = [
            ExportedAPI::FILTER_SMARTLING_TRANSLATION_STRING => 'processString',
            ExportedAPI::FILTER_SMARTLING_TRANSLATION_STRING_RECEIVED => 'processTranslation',
            ExportedAPI::FILTER_SMARTLING_REGISTER_FIELD_FILTER => 'registerFilters',
        ];

        try {
            $this->loadExternalDependencies();

            foreach ($handlers as $hook => $handler) {
                add_filter($hook, [$this, $handler]);
            }

        } catch (SmartlingGutenbergNotFoundException $e) {
            $this->getLogger()->notice($e->getMessage());
        } catch (SmartlingConfigException $e) {
            $this->getLogger()->notice($e->getMessage());
            throw $e;
        }
    }

    /**
     * @param string $blockName
     * @param array  $flatAttributes
     * @return array
     */
    public function processAttributes($blockName, array $flatAttributes)
    {
        $attributes = [];

        if (null === $blockName) {
            return $attributes;
        }

        if (!empty($flatAttributes)) {
            $ve_attributes = var_export($flatAttributes, true);

            $this->getLogger()->debug(vsprintf('Pre filtered block \'%s\' attributes \'%s\'',
                [$blockName, $ve_attributes]));
            $this->postReceiveFiltering($flatAttributes);
            $attributes = $this->preSendFiltering($flatAttributes);
            $this->getLogger()->debug(vsprintf('Post filtered block \'%s\' attributes \'%s\'',
                [$blockName, $ve_attributes]));
        } else {
            $this->getLogger()->debug(vsprintf('No attributes found in block \'%s\'.', [$blockName]));
        }
        return $attributes;
    }

    /**
     * @param string $string
     * @return bool
     */
    public function hasBlocks($string)
    {
        return 0 < (int)preg_match('/<!--\s+wp:/iu', $string);
    }

    /**
     * @param array $data
     * @return string
     */
    private function packData(array $data)
    {
        return base64_encode(serialize($data));
    }

    /**
     * @param $data
     * @return mixed
     */
    private function unpackData($data)
    {
        return unserialize(base64_decode($data));
    }

    /**
     * @param array $block
     * @return \DOMElement
     */
    private function placeBlock(array $block)
    {
        $indexPointer = 0;

        $node = $this->createDomNode(
            static::BLOCK_NODE_NAME,
            [
                'blockName' => $block['blockName'],
                'originalAttributes' => $this->packData($block['attrs']),
            ],
            ''
        );

        foreach ($block['innerContent'] as $chunk) {
            $part = null;
            if (is_string($chunk)) {
                $part = $this->createDomNode(static::CHUNK_NODE_NAME, [], $chunk);
            } else {
                $part = $this->placeBlock($block['innerBlocks'][$indexPointer++]);
            }
            $node->appendChild($part);
        }

        $flatAttributes = $this->getFieldsFilter()->flatternArray($block['attrs']);

        foreach ($this->processAttributes($block['blockName'], $flatAttributes) as $attrName => $attrValue) {
            $node->appendChild($this->createDomNode(
                static::ATTRIBUTE_NODE_NAME,
                ['name' => $attrName],
                $attrValue
            ));
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
        $this->setParams($params);
        $string = static::getCdata($params->getNode());
        if (!$this->hasBlocks($string)) {
            return $params;
        }
        $originalBlocks = $this->parseBlocks($string);
        foreach ($originalBlocks as $block) {
            $node = $this->placeBlock($block);
            $params->getNode()->appendChild($node);
        }
        static::replaceCData($params->getNode(), '');
        return $params;
    }

    /**
     * A wrapper for WP::gutenberg gutenberg_parse_blocks() function
     *
     * @param string $string
     * @return array
     * @throws SmartlingGutenbergParserNotFoundException
     */
    public function parseBlocks($string)
    {
        if (function_exists('\parse_blocks')) {
            return \parse_blocks($string);
        }

        if (function_exists('\gutenberg_parse_blocks')) {
            return \gutenberg_parse_blocks($string);
        }

        throw new SmartlingGutenbergParserNotFoundException('No block parser found.');
    }

    /**
     * @param array $entityFields
     * @return string[] entity fields with added blocks serialized
     */
    public function addPostContentBlocks(array $entityFields)
    {
        if (array_key_exists('post_content', $entityFields) && $this->hasBlocks($entityFields['post_content'])) {
            try {
                foreach ($this->getPostContentBlocks($entityFields['post_content']) as $index => $block) {
                    $entityFields["post_content/blocks/$index"] = serialize_block($block);
                }
            } catch (SmartlingGutenbergParserNotFoundException $e) {
                $this->getLogger()->warning('Block content found while getting translation fields, but no parser available');
            }
        }

        return $entityFields;
    }

    /**
     * @param string $string
     * @return array[]
     * @throws SmartlingGutenbergParserNotFoundException
     */
    public function getPostContentBlocks($string)
    {
        $blocks = $this->parseBlocks($string);

        return array_values(array_filter($blocks, static function ($block) {
            return array_key_exists('blockName', $block) && $block['blockName'] !== null;
        }));
    }

    /**
     * @param $string
     * @return string
     */
    public function normalizeCoreBlocks($string)
    {
        if (function_exists('serialize_blocks')) {
            return \serialize_blocks($this->parseBlocks($string));
        }

        return $string;
    }

    /**
     * @param \DOMNode $node
     * @return array
     */
    public function sortChildNodesContent(\DOMNode $node)
    {
        $chunks = [];
        $attrs = [];
        $nodesToRemove = [];

        foreach ($node->childNodes as $childNode) {
            /**
             * @var \DOMElement $childNode
             */

            switch ($childNode->nodeName) {
                case static::BLOCK_NODE_NAME :
                    $chunks[] = $this->renderTranslatedBlockNode($childNode);
                    break;
                case static::CHUNK_NODE_NAME :
                    $chunks[] = $childNode->nodeValue;
                    break;
                case static::ATTRIBUTE_NODE_NAME :
                    $attrs[$childNode->getAttribute('name')] = $childNode->nodeValue;
                    break;
                default:
                    $this->getLogger()->notice(
                        vsprintf(
                            'Got unexpected child with name=\'%s\' while applying translation.',
                            [$childNode->nodeName]
                        )
                    );
                    break;
            }
            $nodesToRemove[] = $childNode;
        }
        foreach ($nodesToRemove as $item) {
            $node->removeChild($item);
        }
        return [
            'chunks' => $chunks,
            'attributes' => $attrs,
        ];
    }

    /**
     * @param string $blockName
     * @param array  $originalAttributes
     * @param array  $translatedAttributes
     * @return array
     */
    private function processTranslationAttributes($blockName, $originalAttributes, $translatedAttributes)
    {
        $processedAttributes = $originalAttributes;

        if (0 < count($originalAttributes)) {
            $flatAttributes = $this->getFieldsFilter()->flatternArray($originalAttributes);
            $attr = static::maskAttributes($blockName, $flatAttributes);
            $attr = $this->postReceiveFiltering($attr);
            $attr = static::unmaskAttributes($blockName, $attr);
            $filteredAttributes = array_merge($flatAttributes, $attr, $translatedAttributes);
            $processedAttributes = $this->getFieldsFilter()->structurizeArray($filteredAttributes);
        }

        return $this->fixAttributeTypes($originalAttributes, $processedAttributes);
    }

    /**
     * @param \DOMElement $node
     * @return string
     */
    public function renderTranslatedBlockNode(\DOMElement $node)
    {
        $blockName = $node->getAttribute('blockName');
        $blockName = '' === $blockName ? null : $blockName;
        $originalAttributes = $this->unpackData($node->getAttribute('originalAttributes'));
        $sortedResult = $this->sortChildNodesContent($node);
        // simple plain blocks
        if (null === $blockName) {
            return implode('\n', $sortedResult['chunks']);
        }
        $attributes = $this->processTranslationAttributes($blockName, $originalAttributes, $sortedResult['attributes']);
        return $this->renderGutenbergBlock($blockName, $attributes, $sortedResult['chunks']);
    }

    /**
     * @param string $name
     * @param array $attrs
     * @param array $chunks
     * @return string
     */
    public function renderGutenbergBlock($name, array $attrs = [], array $chunks = [])
    {
        $isJson = $this->isJson($attrs);
        if ($isJson) {
            array_walk($attrs, static function (&$value) {
                $value = str_replace('&quot;', '"', $value);
            });
        }
        $attributes = 0 < count($attrs) ? ' ' . json_encode($attrs, JSON_UNESCAPED_UNICODE) : '';
        $content = implode('', $chunks);
        $result = ('' !== $content)
            ? vsprintf('<!-- wp:%s%s -->%s<!-- /wp:%s -->', [$name, $attributes, $content, $name])
            : vsprintf('<!-- wp:%s%s /-->', [$name, $attributes]);

        if ($isJson || (function_exists('acf_has_block_type') && acf_has_block_type($name))) {
            $result = addslashes($result);
        }

        return $result;
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
        $string = static::getCdata($node);

        if ('' === $string) {
            $children = $node->childNodes;
            foreach ($children as $child) {
                /**
                 * @var \DOMElement $child
                 */
                if (static::BLOCK_NODE_NAME === $child->nodeName) {
                    $string .= $this->renderTranslatedBlockNode($child);
                }
            }

            foreach ($children as $child) {
                if (static::BLOCK_NODE_NAME === $child->nodeName) {
                    $node->removeChild($child);
                }
            }
            static::replaceCData($params->getNode(), $string);
        }

        return $this->getParams();
    }

    /**
     * @throws SmartlingGutenbergNotFoundException
     * @throws SmartlingConfigException
     */
    public function loadExternalDependencies()
    {
        if (!defined('ABSPATH')) {
            throw new SmartlingConfigException("Execution requires declared ABSPATH const.");
        }

        $paths = [
            vsprintf('%swp-includes/blocks.php', [ABSPATH]),
            vsprintf('%swp-content/plugins/gutenberg/lib/blocks.php', [ABSPATH]),
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

    /**
     * @param array $originalAttributes
     * @param array $translatedAttributes
     * @return array
     */
    private function fixAttributeTypes(array $originalAttributes, array $translatedAttributes)
    {
        foreach ($translatedAttributes as $key => $value) {
            if (array_key_exists($key, $originalAttributes)) {
                settype($translatedAttributes[$key], gettype($originalAttributes[$key]));
            }
        }

        return $translatedAttributes;
    }

    /**
     * @param array $attrs
     * @return bool
     */
    private function isJson(array $attrs)
    {
        foreach ($attrs as $attr) {
            try {
                $parsed = is_string($attr) ? json_decode(str_replace('&quot;', '"', $attr), true) : null;
            } catch (\Exception $e) {
                $parsed = null;
            }
            if ($parsed !== null && !is_scalar($parsed)) {
                return true;
            }
        }
        return false;
    }
}
