<?php

namespace Smartling\Helpers;

use JetBrains\PhpStorm\ArrayShape;
use Smartling\Base\ExportedAPI;
use Smartling\Exception\EntityNotFoundException;
use Smartling\Exception\SmartlingConfigException;
use Smartling\Exception\SmartlingGutenbergNotFoundException;
use Smartling\Exception\SmartlingGutenbergParserNotFoundException;
use Smartling\Exception\SmartlingNotSupportedContentException;
use Smartling\Extensions\Acf\AcfDynamicSupport;
use Smartling\Helpers\EventParameters\TranslationStringFilterParameters;
use Smartling\Helpers\Serializers\SerializerInterface;
use Smartling\Models\GutenbergBlock;
use Smartling\Replacers\ReplacerFactory;
use Smartling\Replacers\ReplacerInterface;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Tuner\MediaAttachmentRulesManager;
use Smartling\Vendor\JsonPath\JsonObject;

class GutenbergBlockHelper extends SubstringProcessorHelperAbstract
{
    protected const BLOCK_NODE_NAME = 'gutenbergBlock';
    protected const CHUNK_NODE_NAME = 'contentChunk';
    protected const ATTRIBUTE_NODE_NAME = 'blockAttribute';
    private const CDATA_SECTION_NODE_NAME = '#cdata-section';
    private const MAX_NODE_DEPTH = 10;

    public function __construct(
        private AcfDynamicSupport $acfDynamicSupport,
        ContentSerializationHelper $contentSerializationHelper,
        private MediaAttachmentRulesManager $rulesManager,
        private ReplacerFactory $replacerFactory,
        private SerializerInterface $serializer,
        SettingsManager $settingsManager,
        private WordpressFunctionProxyHelper $wpProxy
    )
    {
        parent::__construct($contentSerializationHelper, $settingsManager);
    }

    /**
     * @see register()
     */
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

    public function replacePreTranslateBlockContent(GutenbergBlock $block): GutenbergBlock
    {
        $attributes = $block->getAttributes();
        $replacements = [];
        foreach ($block->getInnerBlocks() as $index => $innerBlock) {
            $block = $block->withInnerBlock($this->replacePreTranslateBlockContent($innerBlock), $index);
        }
        $this->addTemporaryRulesFromAcf($this->getFieldsFilter()->flattenArray($attributes), $block->getBlockName());
        foreach ($this->rulesManager->getGutenbergReplacementRules($block->getBlockName(), $attributes) as $rule) {
            try {
                $replacer = $this->replacerFactory->getReplacer($rule->getReplacerId());
            } catch (EntityNotFoundException) {
                $this->getLogger()->notice("Unable to get replacer replacerId=\"{$rule->getReplacerId()}\" to replace pre translate block");
                return $block;
            }
            $value = $this->getAttributeValue($block, $rule);
            if ($value !== null) {
                $processedValue = $replacer->processAttributeOnUpload($value);
                if ($value !== $processedValue) {
                    $attributes = $this->setAttributeValue($attributes, $rule, $processedValue);
                }
            }
        }

        return $block->withAttributes($this->replaceAttributes($attributes, $replacements));
    }

    public function replacePostTranslateBlockContent(string $original, string $translated, SubmissionEntity $submission = null): string
    {
        if (!$this->hasBlocks($translated)) {
            return $translated;
        }
        $result = '';
        $originalBlocks = $this->parseBlocks($original);
        $translatedBlocks = $this->parseBlocks($translated);
        if (count($originalBlocks) !== count($translatedBlocks)) {
            $this->getLogger()->notice('Counts of blocks differ between original and translated, skipping replacing of post translate block content');
            return $translated;
        }
        foreach ($translatedBlocks as $index => $block) {
            $result .= $this->wpProxy->serialize_block($this->applyPostTranslationReplacers($originalBlocks[$index], $block, $submission)->toArray());
        }
        return $result;
    }

    private function applyPostTranslationReplacers(GutenbergBlock $original, GutenbergBlock $translated, SubmissionEntity $submission = null): GutenbergBlock
    {
        $attributes = $translated->getAttributes();
        $replacements = [];
        foreach ($translated->getInnerBlocks() as $index => $innerBlock) {
            if (!array_key_exists($index, $original->getInnerBlocks())) {
                $this->getLogger()->notice('No original block exists, skip applying post translation replacers for block lockId=' . $translated->getSmartlingLockId());
                continue;
            }
            $translated = $translated->withInnerBlock($this->applyPostTranslationReplacers($original->getInnerBlocks()[$index], $innerBlock, $submission), $index);
        }
        foreach ($this->rulesManager->getGutenbergReplacementRules($translated->getBlockName(), $attributes) as $rule) {
            try {
                $replacer = $this->replacerFactory->getReplacer($rule->getReplacerId());
            } catch (EntityNotFoundException) {
                $this->getLogger()->notice("Unable to get replacer replacerId=\"{$rule->getReplacerId()}\" to replace post translate block");
                return $translated;
            }
            if ($rule->getPropertyPath() === '') {
                $translated = $replacer->processContentOnDownload($original, $translated, $submission);
            } else {
                $attributes = $this->replacePostTranslationAttributes($original, $rule, $translated, $replacer, $submission, $attributes);
            }
        }

        return $translated->withAttributes($this->fixAttributeTypes($original->getAttributes(), $this->replaceAttributes($attributes, $replacements)));
    }

    public function getAttributeValue(GutenbergBlock $block, GutenbergReplacementRule $rule): mixed
    {
        if ($this->rulesManager->isJsonPath($rule->getPropertyPath())) {
            $json = $this->getJson($block);
            if ($json === '') {
                return null;
            }
            $result = (new JsonObject($json))->get($rule->getPropertyPath());
            if (!is_array($result) || count($result) === 0) {
                return null;
            }
            if (count($result) > 1) {
                $this->getLogger()->debug(sprintf("Got more than one result for propertyPath=\"%s\"", addslashes($rule->getPropertyPath())));
            }
            return $result[0];
        }

        foreach ($block->getAttributes() as $attribute => $value) {
            if ($attribute === $rule->getPropertyPath()) {
                return $value;
            }
        }

        return null;
    }

    public function setAttributeValue(array $attributes, GutenbergReplacementRule $rule, $value): array
    {
        if ($this->rulesManager->isJsonPath($rule->getPropertyPath())) {
            return (new JsonObject($attributes))->set($rule->getPropertyPath(), $value)->getValue();
        }
        $attributes[$rule->getPropertyPath()] = $value;
        return $attributes;
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

    /**
     * @see register()
     */
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
    public function parseBlocks($string): array
    {
        if (function_exists('\parse_blocks')) {
            $blocks = \parse_blocks($string);
        } elseif (function_exists('\gutenberg_parse_blocks')) {
            $blocks = \gutenberg_parse_blocks($string);
        } else {
            throw new SmartlingGutenbergParserNotFoundException('No block parser found.');
        }

        return array_map(static function($block) {
            return GutenbergBlock::fromArray($block);
        }, $blocks);
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
                case static::BLOCK_NODE_NAME:
                    $chunks[] = $this->renderTranslatedBlockNode($childNode, $submission, $depth);
                    break;
                case static::CHUNK_NODE_NAME:
                    $chunks[] = $childNode->nodeValue;
                    break;
                case static::ATTRIBUTE_NODE_NAME:
                    $attrs[$childNode->getAttribute('name')] = $childNode->nodeValue;
                    break;
                case self::CDATA_SECTION_NODE_NAME:
                    // processing by this helper is not needed, processed in self::CHUNK_NODE_NAME
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
            $processedAttributes = $this->applyDownloadRules($blockName, $originalAttributes, $filteredAttributes, $submission);
        }

        return $this->fixAttributeTypes($originalAttributes, $processedAttributes);
    }

    private function processTranslationChunks(string $blockName, array $originalAttributes, array $translatedAttributes, array $chunks): array
    {
        $result = [];
        if ($blockName === 'core/image') {
            foreach ($chunks as $chunk) {
                $result[] = preg_replace("/<img(.+)? class=\"([^\"]+)?wp-image-{$originalAttributes['id']}([^\"]+)?\"/", "<img\$1 class=\"\$2wp-image-{$translatedAttributes['id']}\$3\"", $chunk);
            }
        } else {
            return $chunks;
        }
        return $result;
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

        return $this->renderGutenbergBlock(
            $blockName,
            $attributes,
            $this->processTranslationChunks($blockName, $originalAttributes, $attributes, $sortedResult['chunks']),
            $depth,
        );
    }

    public function renderGutenbergBlock(string $name, array $attrs, array $chunks, int $depth): string
    {
        /* Some user content might have JSON with \u0022 to escape quotes.
        After processing XML \u0022 turns into &quot;, which is correct for general content.
        To properly encode the quotes in JSON again we replace &quot; with \" */
        if ($depth === 0 && $this->isPossibleJson($attrs)) {
            array_walk_recursive($attrs, static function (&$value) {
                $value = str_replace('&quot;', '\"', $value);
            });
        }
        $attributes = '';
        if (count($attrs) > 0) {
            try {
                $attributes = ' ' . json_encode($attrs, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            } catch (\JsonException) {
                $this->getLogger()->warning(sprintf('Failed to encode attributes for blockName=%s, attributes will be empty', $name));
            }
        }
        $content = implode('', $chunks);

        return ('' !== $content)
            ? vsprintf('<!-- wp:%s%s -->%s<!-- /wp:%s -->', [$name, $attributes, $content, $name])
            : vsprintf('<!-- wp:%s%s /-->', [$name, $attributes]);
    }

    /**
     * @see register()
     * @noinspection PhpUnused
     */
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
                if (is_array($value)) {
                    $translatedAttributes[$key] = $this->fixAttributeTypes($originalAttributes[$key], $value);
                } elseif (is_scalar($value)) {
                    settype($translatedAttributes[$key], gettype($originalAttributes[$key]));
                }
            }
        }

        return $translatedAttributes;
    }

    private function isPossibleJson(array $attrs): bool
    {
        foreach ($attrs as $attr) {
            try {
                if (is_array($attr)) {
                    return true;
                }
                $parsed = is_string($attr) ? json_decode(str_replace('&quot;', '"', $attr), true, 512, JSON_THROW_ON_ERROR) : null;
            } catch (\Exception) {
                $parsed = null;
            }
            if ($parsed !== null && !is_scalar($parsed)) {
                return true;
            }
        }
        return false;
    }

    public function applyDownloadRules(string $blockName, array $originalAttributes, array $translatedAttributes, ?SubmissionEntity $submission): array
    {
        $hydrated = $this->getFieldsFilter()->structurizeArray($translatedAttributes);
        $replacements = [];
        $this->addTemporaryRulesFromAcf($translatedAttributes, $blockName);
        foreach ($this->rulesManager->getGutenbergReplacementRules($blockName, $hydrated) as $rule) {
            try {
                $replacer = $this->replacerFactory->getReplacer($rule->getReplacerId());
            } catch (EntityNotFoundException) {
                $submissionId = $submission === null ? 'null' : $submission->getId();
                $this->getLogger()->warning("Replacer not found while applying download rules blockName=\"$blockName\", path=\"{$rule->getPropertyPath()}\", submissionId=\"$submissionId\", replacerId=\"{$rule->getReplacerId()}\", skipping");
                continue;
            }
            $originalValue = $this->getAttributeValue(new GutenbergBlock($blockName, $originalAttributes, [], '', []), $rule);
            if ($originalValue !== null) {
                $translatedValue = $this->getAttributeValue(new GutenbergBlock($blockName, $hydrated, [], '', []), $rule);
                $this->getLogger()->debug("ReplacerId=\"{$rule->getReplacerId()}\" processing blockName=\"$blockName\", path=\"{$rule->getPropertyPath()}\", originalValue=\"$originalValue\", translatedValue=\"$translatedValue\"");
                $processedValue = $replacer->processAttributeOnDownload($originalValue, $translatedValue, $submission);
                if ($translatedValue !== $processedValue) {
                    $hydrated = $this->setAttributeValue($hydrated, $rule, $processedValue);
                }
            }
        }

        return $this->replaceAttributes($hydrated, $replacements);
    }

    private function replaceAttributes(array $attributes, array $replacements): array
    {
        if (count($replacements) > 0) {
            try {
                $json = new JsonObject(json_encode($attributes, JSON_THROW_ON_ERROR));
            } catch (\Exception $e) {
                $this->getLogger()->warning("Failed to encode attributes to change values: {$e->getMessage()}");
                return $attributes;
            }
            foreach ($replacements as $path => $value) {
                $json->set($path, $value);
            }
            $result = $json->getValue();
            if ($result === null) {
                $this->getLogger()->warning("Got empty json after replacing attribute values");
            } else {
                $attributes = $result;
            }
        }
        return $attributes;
    }

    private function getJson(GutenbergBlock $block): string
    {
        $matches = [];
        preg_match('~({.+}) /?-->~', $this->wpProxy->serialize_block((new GutenbergBlock($block->getBlockName(), $block->getAttributes(), [], '', []))->toArray()), $matches);
        if (count($matches) < 2) {
            return '';
        }

        return $matches[1];
    }

    private function replacePostTranslationAttributes(
        GutenbergBlock $original,
        GutenbergReplacementRule $rule,
        GutenbergBlock $translated,
        ReplacerInterface $replacer,
        ?SubmissionEntity $submission,
        array $attributes,
    ): array
    {
        $originalValue = $this->getAttributeValue($original, $rule);
        if ($originalValue !== null) {
            $translatedValue = $this->getAttributeValue($translated, $rule);
            // Last argument for $submission here could be null, all attributes based on related ids are then processed when decoding XML. Here we only update clone/ignore values, and handle cloning
            try {
                $processedValue = $replacer->processAttributeOnDownload($originalValue, $translatedValue, $submission);
            } catch (\InvalidArgumentException) {
                $processedValue = $translatedValue;
            }
            if ($translatedValue !== $processedValue) {
                $attributes = $this->setAttributeValue($attributes, $rule, $processedValue);
            }
        }

        return $attributes;
    }

    private function addTemporaryRulesFromAcf(array $flatAttributes, ?string $blockName): void
    {
        if ($blockName === null) {
            return;
        }
        foreach (array_keys($flatAttributes) as $attribute) {
            $replacerId = $this->acfDynamicSupport->getReplacerIdForField($flatAttributes, $attribute);
            if ($replacerId !== null) {
                $this->getLogger()->debug("Adding temporary rule for blockName=$blockName, propertyPath=$attribute, replacerId=$replacerId");
                $this->rulesManager->addTemporaryRule(new GutenbergReplacementRule(
                    $blockName,
                    '$.' . str_replace(FieldsFilterHelper::ARRAY_DIVIDER, '.', $attribute),
                    $replacerId,
                ));
            }
        }
    }
}
