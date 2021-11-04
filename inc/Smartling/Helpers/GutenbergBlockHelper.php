<?php

namespace Smartling\Helpers;

use JetBrains\PhpStorm\ArrayShape;
use Smartling\Base\ExportedAPI;
use Smartling\Exception\EntityNotFoundException;
use Smartling\Exception\SmartlingConfigException;
use Smartling\Exception\SmartlingGutenbergNotFoundException;
use Smartling\Exception\SmartlingGutenbergParserNotFoundException;
use Smartling\Exception\SmartlingNotSupportedContentException;
use Smartling\Helpers\EventParameters\TranslationStringFilterParameters;
use Smartling\Helpers\Serializers\SerializerInterface;
use Smartling\Models\GutenbergBlock;
use Smartling\Replacers\ReplacerFactory;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Tuner\MediaAttachmentRulesManager;

class GutenbergBlockHelper extends SubstringProcessorHelperAbstract
{
    protected const BLOCK_NODE_NAME = 'gutenbergBlock';
    protected const CHUNK_NODE_NAME = 'contentChunk';
    protected const ATTRIBUTE_NODE_NAME = 'blockAttribute';
    private const MAX_NODE_DEPTH = 10;

    private MediaAttachmentRulesManager $rulesManager;
    private ReplacerFactory $replacerFactory;
    private SerializerInterface $serializer;

    public function __construct(MediaAttachmentRulesManager $rulesManager, ReplacerFactory $replacerFactory, SerializerInterface $serializer)
    {
        parent::__construct();
        $this->replacerFactory = $replacerFactory;
        $this->rulesManager = $rulesManager;
        $this->serializer = $serializer;
    }

    public function registerFilters(array $definitions): array
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

    public function register(): void
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

    public function processAttributes(?string $blockName, array $flatAttributes): array
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

    public function hasBlocks(string $string): bool
    {
        return 0 < (int)preg_match('/<!--\s+wp:/iu', $string);
    }

    private function packData(array $data): string
    {
        return $this->serializer->serialize($data);
    }

    private function unpackData(string $data): array
    {
        return $this->serializer->unserialize($data);
    }

    private function placeBlock(GutenbergBlock $block): \DOMElement
    {
        $indexPointer = 0;

        $node = $this->createDomNode(
            static::BLOCK_NODE_NAME,
            [
                'blockName' => $block->getBlockName(),
                'originalAttributes' => $this->packData($block->getAttributes()),
            ],
            ''
        );

        foreach ($block->getInnerContent() as $chunk) {
            $part = null;
            if (is_string($chunk)) {
                $part = $this->createDomNode(static::CHUNK_NODE_NAME, [], $chunk);
            } else {
                $part = $this->placeBlock($block->getInnerBlocks()[$indexPointer++]);
            }
            $node->appendChild($part);
        }

        $flatAttributes = $this->getFieldsFilter()->flattenArray($block->getAttributes());

        foreach ($this->processAttributes($block->getBlockName(), $flatAttributes) as $attrName => $attrValue) {
            $node->appendChild($this->createDomNode(
                static::ATTRIBUTE_NODE_NAME,
                ['name' => $attrName],
                $attrValue
            ));
        }

        return $node;
    }

    public function processString(TranslationStringFilterParameters $params): TranslationStringFilterParameters
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
     * @return GutenbergBlock[]
     * @throws SmartlingGutenbergParserNotFoundException
     */
    public function parseBlocks($string)
    {
        if (function_exists('\parse_blocks')) {
            $blocks = \parse_blocks($string);
        } elseif (function_exists('\gutenberg_parse_blocks')) {
            /** @noinspection PhpUndefinedFunctionInspection */
            $blocks = \gutenberg_parse_blocks($string);
        } else {
            throw new SmartlingGutenbergParserNotFoundException('No block parser found.');
        }

        return array_map(static function($block) {
            return GutenbergBlock::fromArray($block);
        }, $blocks);
    }

    /**
     * @return string[] entity fields with added blocks serialized
     */
    public function addPostContentBlocks(array $entityFields): array
    {
        if (array_key_exists('post_content', $entityFields) && $this->hasBlocks($entityFields['post_content'])) {
            try {
                foreach ($this->getPostContentBlocks($entityFields['post_content']) as $index => $block) {
                    $entityFields = $this->addBlock($entityFields, "post_content/blocks/$index", $block);
                }
            } catch (SmartlingGutenbergParserNotFoundException $e) {
                $this->getLogger()->warning('Block content found while getting translation fields, but no parser available');
            }
        }

        return $entityFields;
    }

    private function addBlock(array $entityFields, string $baseKey, GutenbergBlock $block): array
    {
        $entityFields[$baseKey] = serialize_block($block->toArray());
        foreach ($block->getInnerBlocks() as $index => $innerBlock) {
            $entityFields = $this->addBlock($entityFields, "$baseKey/$index", $innerBlock);
        }

        return $entityFields;
    }

    /**
     * @return GutenbergBlock[]
     * @throws SmartlingGutenbergParserNotFoundException
     */
    public function getPostContentBlocks(string $string): array
    {
        return $this->parseBlocks($string);
    }

    #[ArrayShape(['attributes' => 'array', 'chunks' => 'array'])]
    public function sortChildNodesContent(\DOMNode $node, SubmissionEntity $submission, int $depth): array
    {
        if ($depth > self::MAX_NODE_DEPTH) {
            throw new SmartlingNotSupportedContentException('Maximum child node nesting depth (' . self::MAX_NODE_DEPTH . ') exceeded');
        }
        $chunks = [];
        $attrs = [];
        $nodesToRemove = [];

        foreach ($node->childNodes as $childNode) {
            /**
             * @var \DOMElement $childNode
             */

            switch ($childNode->nodeName) {
                case static::BLOCK_NODE_NAME :
                    $chunks[] = $this->renderTranslatedBlockNode($childNode, $submission, $depth);
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

    private function processTranslationAttributes(SubmissionEntity $submission, string $blockName, array $originalAttributes, array $translatedAttributes): array
    {
        $processedAttributes = $originalAttributes;

        if (0 < count($originalAttributes)) {
            $flatAttributes = $this->getFieldsFilter()->flattenArray($originalAttributes);
            $attr = static::maskAttributes($blockName, $flatAttributes);
            $attr = $this->postReceiveFiltering($attr);
            $attr = static::unmaskAttributes($blockName, $attr);
            $filteredAttributes = array_merge($flatAttributes, $attr, $translatedAttributes);
            $processedAttributes = $this->getFieldsFilter()->structurizeArray($this->applyDownloadRules($submission, $blockName, $filteredAttributes));
        }

        return $this->fixAttributeTypes($originalAttributes, $processedAttributes);
    }

    public function renderTranslatedBlockNode(\DOMElement $node, SubmissionEntity $submission, int $depth): string
    {
        $blockName = $node->getAttribute('blockName');
        $blockName = '' === $blockName ? null : $blockName;
        $originalAttributes = $this->unpackData($node->getAttribute('originalAttributes'));
        $sortedResult = $this->sortChildNodesContent($node, $submission, $depth + 1);
        // simple plain blocks
        if (null === $blockName) {
            return implode('\n', $sortedResult['chunks']);
        }
        $attributes = $this->processTranslationAttributes($submission, $blockName, $originalAttributes, $sortedResult['attributes']);
        return $this->renderGutenbergBlock($blockName, $attributes, $sortedResult['chunks'], $depth);
    }

    public function renderGutenbergBlock(string $name, array $attrs, array $chunks, int $depth): string
    {
        /* Some user content might have JSON with \u0022 to escape quotes.
        After processing XML \u0022 turns into &quot;, which is correct for general content.
        To properly encode the quotes in JSON again we replace &quot; with " */
        $needsQuotes = $this->isJson($attrs);
        if ($needsQuotes && $depth === 0) {
            array_walk_recursive($attrs, static function (&$value) {
                $value = str_replace('&quot;', '"', $value);
            });
        }
        $attributes = 0 < count($attrs) ? ' ' . json_encode($attrs, JSON_UNESCAPED_UNICODE) : '';
        $content = implode('', $chunks);
        $result = ('' !== $content)
            ? vsprintf('<!-- wp:%s%s -->%s<!-- /wp:%s -->', [$name, $attributes, $content, $name])
            : vsprintf('<!-- wp:%s%s /-->', [$name, $attributes]);

        // Only apply slashes for root depth: ACF blocks and blocks that we have previously replaced quotes in
        if ($depth === 0 && ($needsQuotes || (function_exists('acf_has_block_type') && acf_has_block_type($name)))) {
            $result = addslashes($result);
        }

        return $result;
    }

    public function processTranslation(TranslationStringFilterParameters $params): TranslationStringFilterParameters
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
                    $string .= $this->renderTranslatedBlockNode($child, $params->getSubmission(), 0);
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
    public function loadExternalDependencies(): void
    {
        if (!defined('ABSPATH')) {
            throw new SmartlingConfigException("Execution requires declared ABSPATH const.");
        }

        $paths = [
            vsprintf('%swp-includes/blocks.php', [ABSPATH]),
            vsprintf('%swp-content/plugins/gutenberg/lib/blocks.php', [ABSPATH]),
        ];


        foreach ($paths as $path) {
            if (file_exists($path) && is_readable($path)) {
                require_once $path;
                return;
            }
        }

        throw new SmartlingGutenbergNotFoundException("Gutenberg class not found. Disabling GutenbergSupport.");
    }

    private function fixAttributeTypes(array $originalAttributes, array $translatedAttributes): array
    {
        foreach ($translatedAttributes as $key => $value) {
            if (array_key_exists($key, $originalAttributes)) {
                settype($translatedAttributes[$key], gettype($originalAttributes[$key]));
            }
        }

        return $translatedAttributes;
    }

    private function isJson(array $attrs): bool
    {
        foreach ($attrs as $attr) {
            try {
                if (is_array($attr)) {
                    return true;
                }
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

    private function applyDownloadRules(SubmissionEntity $submission, string $blockName, array $attributes): array
    {
        foreach ($attributes as $attribute => $value) {
            foreach ($this->rulesManager->getGutenbergReplacementRules($blockName, $attribute) as $rule) {
                try {
                    $replacer = $this->replacerFactory->getReplacer($rule->getReplacerId());
                } catch (EntityNotFoundException $e) {
                    $this->getLogger()->warning("Replacer not found while processing blockName=\"$blockName\", attribute=\"$attribute\", submissionId=\"{$submission->getId()}\", replacerId=\"{$rule->getReplacerId()}\", skipping");
                    continue;
                }
                $attributes[$attribute] = $replacer->processOnDownload($submission, $value);
            }
        }

        return $attributes;
    }
}
