<?php

namespace Smartling\Helpers;

use DOMAttr;
use DOMCdataSection;
use DOMDocument;
use DOMXPath;
use Smartling\Base\ExportedAPI;
use Smartling\Bootstrap;
use Smartling\Exception\InvalidXMLException;
use Smartling\Exception\SmartlingDbException;
use Smartling\Helpers\EventParameters\TranslationStringFilterParameters;
use Smartling\Helpers\Serializers\SerializerInterface;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Settings\SettingsManager;
use Smartling\Services\GlobalSettingsManager;
use Smartling\Submissions\SubmissionEntity;

class XmlHelper
{
    use LoggerSafeTrait;

    public function __construct(
        private ContentSerializationHelper $contentSerializationHelper,
        private SerializerInterface $serializer,
        private SettingsManager $settingsManager,
    ) {
    }

    private const XML_ROOT_NODE_NAME = 'data';

    private const XML_STRING_NODE_NAME = 'string';

    private const XML_SOURCE_NODE_NAME = 'source';

    private function initXml(): DOMDocument
    {
        return new DOMDocument('1.0', 'UTF-8');
    }

    private function setTranslationComments(DOMDocument $document, ?ConfigurationProfileEntity $profile): DOMDocument
    {
        $comments = [
            'smartling.translate_paths = data/string/',
            'smartling.string_format_paths = html : data/string/',
            'smartling.source_key_paths = data/{string.key}',
            'smartling.variants_enabled = true',
            'Smartling Wordpress Connector version: ' . Bootstrap::getCurrentVersion(),
            'Wordpress installation host: ' . Bootstrap::getHttpHostName(),
            'smartling.placeholder_format_custom = #sl-start#.+?#sl-end#',
        ];

        if ($profile !== null) {
            $comments[] = 'Profile config:';
            try {
                $comments[] = $this->escapeCommentString(json_encode(
                    $profile->toArray(),
                    JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR,
                ));
            } catch (\Throwable $e) {
                $this->getLogger()->warning('Failed to encode profile config: ' . $e->getMessage());
            }
        }

        foreach (explode("\n", GlobalSettingsManager::getCustomDirectives()) as $comment) {
            if ($comment !== '') {
                $comments[] = $comment;
            }
        }

        foreach ($comments as $comment) {
            $document->appendChild($document->createComment(" $comment "));
        }

        return $document;
    }

    private function encodeSource(array $source): string
    {
        return "\n" . implode("\n", str_split($this->serializer->serialize($source), 120)) . "\n";
    }

    private function decodeSource(string $source): array
    {
        return $this->serializer->unserialize($source);
    }

    public function xmlEncode(array $source, SubmissionEntity $submission, array $originalContent = []): string
    {
        $this->getLogger()->debug(sprintf('Started creating XML for fields: %s', base64_encode(var_export($source, true))));
        try {
            $profile = $this->settingsManager->getProfileBySubmission($submission);
        } catch (SmartlingDbException) {
            $profile = null;
        }
        $xml = $this->setTranslationComments($this->initXml(), $profile);
        $settings = $this->contentSerializationHelper->prepareFieldProcessorValues($submission);
        $rootNode = $xml->createElement(self::XML_ROOT_NODE_NAME);
        global $shortcode_tags;
        $total = count($source);
        $startedAt = microtime(true);
        $this->getLogger()->debug(sprintf(
            'Creating XML nodes: rowCount=%d, registeredShortcodeCount=%d, activePlugins="%s", activeNetworkPlugins="%s"',
            $total,
            count($shortcode_tags ?? []),
            implode(',', (array)get_option('active_plugins', [])),
            implode(',', array_keys((array)get_site_option('active_sitewide_plugins', []))),
        ));
        $index = 0;
        foreach ($source as $name => $value) {
            ++$index;
            $this->getLogger()->debug(sprintf(
                'Creating XML node %d/%d: name="%s", valueLength=%d, elapsed=%.2fs, peakMemory=%dB',
                $index,
                $total,
                $name,
                strlen((string)$value),
                microtime(true) - $startedAt,
                memory_get_peak_usage(true),
            ));
            $rowStartedAt = microtime(true);
            $rootNode->appendChild($this->rowToXMLNode($xml, $name, $value, $settings['key'], $submission));
            $rowElapsed = microtime(true) - $rowStartedAt;
            if ($rowElapsed > 0.5) {
                $this->getLogger()->debug(sprintf('Slow XML node %d/%d: name="%s", took=%.2fs', $index, $total, $name, $rowElapsed));
            }
        }
        $xml->appendChild($rootNode);
        $this->getLogger()->debug(sprintf('Created %d XML nodes in %.2fs, adding source', $total, microtime(true) - $startedAt));

        $this->addSource($rootNode, $xml, $originalContent);
        $this->getLogger()->debug(sprintf('Added source, elapsed=%.2fs, peakMemory=%dB', microtime(true) - $startedAt, memory_get_peak_usage(true)));

        $result = $xml->saveXML();
        $this->getLogger()->debug(sprintf('Saved XML, elapsed=%.2fs, size=%d', microtime(true) - $startedAt, strlen((string)$result)));

        return $result;
    }

    private function addSource(\DOMNode $root, \DOMDocument $xml, $content): void
    {
        $node = $xml->createElement(self::XML_SOURCE_NODE_NAME);
        $node->appendChild(new DOMCdataSection($this->encodeSource($content)));
        $root->appendChild($node);
    }

    private function rowToXMLNode(DOMDocument $document, $name, $value, $keySettings, SubmissionEntity $submission)
    {
        $node = $document->createElement(self::XML_STRING_NODE_NAME);
        $node->setAttributeNode(new DOMAttr('name', $name));
        foreach ($keySettings as $key => $fields) {
            foreach ($fields as $field) {
                if (str_contains($name, $field)) {
                    $node->setAttributeNode(new DOMAttr('key', $key));
                }
            }
        }
        $node->appendChild(new DOMCdataSection($value));
        $params = new TranslationStringFilterParameters();
        $params->setDom($document);
        $params->setNode($node);
        $params->setFilterSettings($this->contentSerializationHelper->prepareFieldProcessorValues($submission));
        $params->setSubmission($submission);
        $params = apply_filters(ExportedAPI::FILTER_SMARTLING_TRANSLATION_STRING, $params);

        return $params->getNode();
    }

    /**
     * @throws InvalidXMLException
     */
    private function prepareXPath(string $xmlString): DOMXPath
    {
        $this->preventLoadingExternalEntities();

        $xml = $this->initXml();
        $result = @$xml->loadXML($xmlString);
        if (false === $result) {
            throw new InvalidXMLException('Invalid XML Contents');
        }

        return new DOMXPath($xml);
    }

    private function preventLoadingExternalEntities(): void
    {
        // libxml_disable_entity_loader is deprecated in php8, using a null loader instead
        libxml_set_external_entity_loader(static function () {
            return null;
        });
    }

    /**
     * @throws InvalidXMLException
     */
    public function xmlDecode(string $content, SubmissionEntity $submission): DecodedXml
    {
        if ($content === '') {
            $this->getLogger()->debug('Skipped XML decoding: empty content');
            return new DecodedXml([], []);
        }
        $this->getLogger()->debug(sprintf('Starting XML file decoding : %s', base64_encode(var_export($content, true))));
        $xpath = $this->prepareXPath($content);
        return new DecodedXml($this->getFields($xpath, $submission), $this->getSource($xpath));
    }

    private function getFields(DOMXPath $xpath, SubmissionEntity $submission): array
    {
        $nodeList = $xpath->query('/' . self::XML_ROOT_NODE_NAME . '/' . self::XML_STRING_NODE_NAME);
        $fields = [];
        for ($i = 0; $i < $nodeList->length; $i++) {
            $item = $nodeList->item($i);
            if ($item === null) {
                break;
            }
            $name = $item->attributes->getNamedItem('name')->nodeValue;
            $params = new TranslationStringFilterParameters();
            $params->setDom($item->ownerDocument);
            $params->setNode($item);
            $params->setFilterSettings($this->contentSerializationHelper->prepareFieldProcessorValues($submission));
            $params->setSubmission($submission);

            $params = apply_filters(ExportedAPI::FILTER_SMARTLING_TRANSLATION_STRING_RECEIVED, $params);

            $nodeValue = $params->getNode()->nodeValue;
            $fields[$name] = $nodeValue;
        }

        foreach ($fields as $key => $value) {
            if (is_numeric($value) && is_string($value)) {
                $fields[$key] += 0;
            }
        }

        return $fields;
    }

    private function getSource(DOMXPath $xPath): array
    {
        $query = $xPath->query('/' . self::XML_ROOT_NODE_NAME . '/' . self::XML_SOURCE_NODE_NAME);
        if ($query->length > 0) {
            try {
                return $this->decodeSource($query[0]->nodeValue);
            } catch (\Exception $e) {
                $this->getLogger()->debug("Failed to decode source: " . $e->getMessage());
            }
        }
        return [];
    }

    private function escapeCommentString(string $string): string
    {
        return preg_replace('~-{2,}~', '-​-', $string);
    }
}
