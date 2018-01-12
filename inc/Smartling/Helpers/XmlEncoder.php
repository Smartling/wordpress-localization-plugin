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
use Smartling\Submissions\SubmissionEntity;

/**
 * Class XmlEncoder
 * Encodes given array into XML string and backward
 * @package Smartling\Processors
 */
class XmlEncoder
{

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
        Bootstrap::getLogger()->debug($message);
    }

    private static $magicComments = [
        'smartling.translate_paths = data/string/',
        'smartling.string_format_paths = html : data/string/',
        'smartling.source_key_paths = data/{string.key}',
        'smartling.variants_enabled = true',
    ];

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

    /**
     * Sets comments about translation type (html)
     *
     * @param DOMDocument $document
     *
     * @return DOMDocument
     */
    private static function setTranslationComments(DOMDocument $document)
    {
        foreach (self::$magicComments as $commentString) {
            $document->appendChild($document->createComment(vsprintf(' %s ', [$commentString])));
        }

        $additionalComments = [
            'Smartling Wordpress Connector version: ' . Bootstrap::getCurrentVersion(),
            'Wordpress installation host: ' . Bootstrap::getHttpHostName(),
            vsprintf(
                ' smartling.placeholder_format_custom = %s ',
                [
                    ShortcodeHelper::getShortcodeMaskRegexp(),
                ]
            ),
        ];

        foreach ($additionalComments as $extraComment) {
            $document->appendChild($document->createComment(vsprintf(' %s ', [$extraComment])));
        }

        return $document;
    }


    private static function encodeSource($source, $stringLength = 120)
    {
        return "\n" . implode("\n", str_split(base64_encode(serialize($source)), $stringLength)) . "\n";
    }

    private static function decodeSource($source)
    {
        return unserialize(base64_decode($source));
    }

    /**
     * @param array            $source
     * @param array            $originalContent
     * @param SubmissionEntity $submission
     *
     * @return string
     */
    public static function xmlEncode(array $source, array $originalContent = [], SubmissionEntity $submission)
    {
        self::logMessage(vsprintf('Started creating XML for fields: %s', [base64_encode(var_export($source, true))]));
        $xml = self::setTranslationComments(self::initXml());
        $settings = self::getFieldProcessingParams();
        $keySettings = &$settings['key'];
        $rootNode = $xml->createElement(self::XML_ROOT_NODE_NAME);
        foreach ($source as $name => $value) {
            $rootNode->appendChild(self::rowToXMLNode($xml, $name, $value, $keySettings, $submission));
        }
        $xml->appendChild($rootNode);
        $sourceNode = $xml->createElement(self::XML_SOURCE_NODE_NAME);
        $sourceNode->appendChild(new DOMCdataSection(self::encodeSource($originalContent)));
        $rootNode->appendChild($sourceNode);

        return $xml->saveXML();
    }

    /**
     * @inheritdoc
     */
    private static function rowToXMLNode(DOMDocument $document, $name, $value, & $keySettings, SubmissionEntity $submission)
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
        $xml = self::initXml();
        $result = @$xml->loadXML($xmlString);
        if (false === $result) {
            throw new InvalidXMLException('Invalid XML Contents');
        }

        return new DOMXPath($xml);
    }

    /**
     * @param string           $content
     * @param SubmissionEntity $submission
     *
     * @return array
     */
    public static function xmlDecode($content, SubmissionEntity $submission)
    {
        self::logMessage(vsprintf('Starting XML file decoding : %s', [base64_encode(var_export($content, true))]));
        $xpath = self::prepareXPath($content);
        $stringPath = '/data/string';
        $nodeList = $xpath->query($stringPath);
        $fields = [];
        for ($i = 0; $i < $nodeList->length; $i++) {
            $item = $nodeList->item($i);
            /**
             * @var \DOMNode $item
             */

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
}
