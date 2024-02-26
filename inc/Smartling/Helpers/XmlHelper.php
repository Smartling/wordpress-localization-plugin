<?php

namespace Smartling\Helpers;

use DOMAttr;
use DOMCdataSection;
use DOMDocument;
use DOMXPath;
use Smartling\Base\ExportedAPI;
use Smartling\Bootstrap;
use Smartling\Exception\InvalidXMLException;
use Smartling\Helpers\EventParameters\TranslationStringFilterParameters;
use Smartling\Helpers\Serializers\SerializerInterface;
use Smartling\MonologWrapper\MonologWrapper;
use Smartling\Services\GlobalSettingsManager;
use Smartling\Submissions\SubmissionEntity;

class XmlHelper
{
    private SerializerInterface $serializer;

    public function __construct(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
    }

    /**
     * @return mixed
     */
    private static function getFieldProcessingParams()
    {
        return Bootstrap::getContainer()->getParameter('field.processor');
    }

    /**
     * Logs XML related message.
     *
     * @param $message
     */
    public static function logMessage($message)
    {
        MonologWrapper::getLogger(get_called_class())->debug($message);
    }

    const XML_ROOT_NODE_NAME = 'data';

    const XML_STRING_NODE_NAME = 'string';

    const XML_SOURCE_NODE_NAME = 'source';

    /**
     * @return DOMDocument
     */
    private static function initXml()
    {
        return new DOMDocument('1.0', 'UTF-8');
    }

    private function setTranslationComments(DOMDocument $document): DOMDocument
    {
        $comments = [
            'smartling.translate_paths = data/string/',
            'smartling.string_format_paths = html : data/string/',
            'smartling.source_key_paths = data/{string.key}',
            'smartling.variants_enabled = true',
            'Smartling Wordpress Connector version: ' . Bootstrap::getCurrentVersion(),
            'Wordpress installation host: ' . Bootstrap::getHttpHostName(),
            'smartling.placeholder_format_custom = ' . ShortcodeHelper::getMaskRegexp(),
            'smartling.placeholder_format_custom = #sl-start#.+?#sl-end#',
        ];

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

    /**
     * TODO: refactor this and SmartlingCoreTrait::prepareFieldProcessorValues to get rid of self::getFieldProcessingParams() in favour of sending them as an argument
     */
    public function xmlEncode(array $source, SubmissionEntity $submission, array $originalContent = []): string
    {
        static::logMessage(vsprintf('Started creating XML for fields: %s', [base64_encode(var_export($source, true))]));
        $xml = $this->setTranslationComments(self::initXml());
        $settings = self::getFieldProcessingParams();
        $rootNode = $xml->createElement(self::XML_ROOT_NODE_NAME);
        foreach ($source as $name => $value) {
            $rootNode->appendChild(self::rowToXMLNode($xml, $name, $value, $settings['key'], $submission));
        }
        $xml->appendChild($rootNode);

        $this->addSource($rootNode, $xml, $originalContent);

        return $xml->saveXML();
    }

    private function addSource(\DOMNode $root, \DOMDocument $xml, $content): void
    {
        $node = $xml->createElement(self::XML_SOURCE_NODE_NAME);
        $node->appendChild(new DOMCdataSection($this->encodeSource($content)));
        $root->appendChild($node);
    }

    private static function rowToXMLNode(DOMDocument $document, $name, $value, $keySettings, SubmissionEntity $submission)
    {
        $node = $document->createElement(self::XML_STRING_NODE_NAME);
        $node->setAttributeNode(new DOMAttr('name', $name));
        foreach ($keySettings as $key => $fields) {
            foreach ($fields as $field) {
                if (false !== strpos($name, $field)) {
                    $node->setAttributeNode(new DOMAttr('key', $key));
                }
            }
        }
        $node->appendChild(new DOMCdataSection($value));
        $params = new TranslationStringFilterParameters();
        $params->setDom($document);
        $params->setNode($node);
        $params->setFilterSettings(self::getFieldProcessingParams());
        $params->setSubmission($submission);
        $params = apply_filters(ExportedAPI::FILTER_SMARTLING_TRANSLATION_STRING, $params);

        return $params->getNode();
    }

    /**
     * @param string $xmlString
     *
     * @return DOMXPath
     * @throws InvalidXMLException
     */
    private static function prepareXPath($xmlString)
    {
        self::preventLoadingExternalEntities();

        $xml = self::initXml();
        $result = @$xml->loadXML($xmlString);
        if (false === $result) {
            throw new InvalidXMLException('Invalid XML Contents');
        }

        return new DOMXPath($xml);
    }

    private static function preventLoadingExternalEntities(): void
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
            static::logMessage('Skipped XML decoding: empty content');
            return new DecodedXml([], []);
        }
        static::logMessage(vsprintf('Starting XML file decoding : %s', [base64_encode(var_export($content, true))]));
        $xpath = self::prepareXPath($content);
        return new DecodedXml(self::getFields($xpath, $submission), $this->getSource($xpath));
    }

    /**
     * @param string $xml
     *
     * @return bool
     */
    public static function hasStringsForTranslation($xml)
    {
        $xpath = self::prepareXPath($xml);
        $stringPath = '/data/string';
        $nodeList = $xpath->query($stringPath);

        return $nodeList->length > 0;
    }

    /**
     * @param DOMXPath $xpath
     * @param SubmissionEntity $submission
     * @return array
     */
    private static function getFields(DOMXPath $xpath, SubmissionEntity $submission)
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
            $params->setFilterSettings(self::getFieldProcessingParams());
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
                self::logMessage("Failed to decode source: " . $e->getMessage());
            }
        }
        return [];
    }
}
