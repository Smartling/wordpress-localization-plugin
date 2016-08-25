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

/**
 * Class XmlEncoder
 *
 * Encodes given array into XML string and backward
 *
 * @package Smartling\Processors
 */
class XmlEncoder
{

    /**
     * Logs XML related message.
     * Controlled by logger.smartling_verbose_output_for_xml_coding bool value
     *
     * @param $message
     */
    public static function logMessage($message)
    {
        if (true === (bool)Bootstrap::getContainer()->getParameter('logger.smartling_verbose_output_for_xml_coding')) {
            Bootstrap::getLogger()->debug($message);
        }
    }

    private static $magicComments = [
        'smartling.translate_paths = data/string/'  ,
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
        $xml = new DOMDocument('1.0', 'UTF-8');

        return $xml;
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
                    ShortcodeHelper::getShortcodeMaskRegexp()
                ]
            )
        ];

        foreach ($additionalComments as $extraComment) {
            $document->appendChild($document->createComment(vsprintf(' %s ', [$extraComment])));
        }

        return $document;
    }

    /**
     * @param array  $array
     * @param string $base
     * @param string $divider
     *
     * @return array
     */
    protected static function flatternArray(array $array, $base = '', $divider = '/')
    {
        $output = [];

        foreach ($array as $key => $element) {

            $path = '' === $base ? $key : implode($divider, [$base, $key]);

            $valueType = gettype($element);

            switch ($valueType) {
                case 'array':
                    $tmp = self::flatternArray($element, $path);
                    $output = array_merge($output, $tmp);
                    break;
                case 'NULL':
                case 'boolean':
                case 'string':
                case 'integer':
                case 'double':
                    $output[$path] = (string)$element;
                    break;
                case 'unknown type':
                case 'resource':
                case 'object':
                default:
                    $message = vsprintf('Unsupported type \'%s\' found in scope for translation. Skipped. Contents: \'%s\'.',
                                        [$valueType, var_export($element, true),]);
                    self::logMessage($message);
            }
        }

        return $output;
    }

    /**
     * @param array  $flatArray
     * @param string $divider
     *
     * @return array
     */
    protected static function structurizeArray(array $flatArray, $divider = '/')
    {
        $output = [];

        foreach ($flatArray as $key => $element) {
            $pathElements = explode($divider, $key);
            $pointer = &$output;
            for ($i = 0; $i < (count($pathElements) - 1); $i++) {
                if (!isset($pointer[$pathElements[$i]])) {
                    $pointer[$pathElements[$i]] = [];
                }
                $pointer = &$pointer[$pathElements[$i]];
            }
            $pointer[end($pathElements)] = $element;
        }

        return $output;
    }

    /**
     * @param array $source
     *
     * @return array
     */
    private static function normalizeSource(array $source)
    {
        if (array_key_exists('meta', $source) && is_array($source['meta'])) {
            $pointer = &$source['meta'];
            foreach ($pointer as & $value) {
                if (is_array($value) && 1 === count($value)) {
                    $value = reset($value);
                }
            }
        }

        return $source;
    }

    /**
     * @return mixed
     */
    private static function getFieldProcessingParams()
    {
        return Bootstrap::getContainer()->getParameter('field.processor');
    }

    /**
     * @param array $array
     * @param array $list
     *
     * @return array
     */
    private static function removeFields($array, array $list)
    {
        $rebuild = [];
        if ([] === $list) {
            return $array;
        }
        $pattern = '#(' . implode('|', $list) . ')$#us';
        foreach ($array as $key => $value) {
            if (1 === preg_match($pattern, $key)) {
                $debugMessage = vsprintf('Removed field by name \'%s\' because of configuration.', [$key]);
                self::logMessage($debugMessage);
                continue;
            } else {
                $rebuild[$key] = $value;
            }
        }

        return $rebuild;
    }

    /**
     * @param $array
     *
     * @return array
     */
    private static function removeEmptyFields($array)
    {
        $rebuild = [];
        foreach ($array as $key => $value) {
            if (empty($value)) {
                $debugMessage = vsprintf('Removed empty field \'%s\'.', [$key]);
                self::logMessage($debugMessage);
                continue;
            }
            $rebuild[$key] = $value;
        }

        return $rebuild;
    }

    /**
     * @param $array
     * @param $list
     *
     * @return array
     */
    private static function removeValuesByRegExp($array, $list)
    {
        $rebuild = [];
        foreach ($array as $key => $value) {
            foreach ($list as $item) {
                if (preg_match("/{$item}/us", $value)) {
                    $debugMessage = vsprintf('Removed field by value: filedName:\'%s\' fieldValue:\'%s\' filter:\'%s\'.',
                                             [$key, $value, $item]);
                    self::logMessage($debugMessage);
                    continue 2;
                }
            }
            $rebuild[$key] = $value;
        }

        return $rebuild;
    }

    public static function prepareSourceArray($sourceArray, $strategy = 'send')
    {
        $sourceArray = self::normalizeSource($sourceArray);

        if (array_key_exists('meta', $sourceArray) && is_array($sourceArray['meta'])) {
            foreach ($sourceArray['meta'] as & $value) {
                if (is_array($value) && array_key_exists('entity', $value) && array_key_exists('meta', $value)) {
                    // nested object detected
                    $value = self::prepareSourceArray($value, $strategy);
                }

                $value = maybe_unserialize($value);
            }
        }
        $sourceArray = self::flatternArray($sourceArray);

        $settings = self::getFieldProcessingParams();

        if ('send' === $strategy) {
            $sourceArray = self::removeFields($sourceArray, $settings['ignore']);
            $sourceArray = self::removeFields($sourceArray, $settings['copy']['name']);
            $sourceArray = self::removeValuesByRegExp($sourceArray, $settings['copy']['regexp']);
            $sourceArray = self::removeEmptyFields($sourceArray);
        }

        return $sourceArray;

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
     * @param array $source
     *
     * @return string
     */
    public static function xmlEncode(array $source)
    {
        self::logMessage(vsprintf('Started creating XML for fields: %s', [base64_encode(var_export($source, true))]));
        $originalSource = $source;
        $source = self::prepareSourceArray($source);
        $xml = self::setTranslationComments(self::initXml());
        $settings = self::getFieldProcessingParams();
        $keySettings = &$settings['key'];
        $rootNode = $xml->createElement(self::XML_ROOT_NODE_NAME);
        foreach ($source as $name => $value) {
            $rootNode->appendChild(self::rowToXMLNode($xml, $name, $value, $keySettings));
        }
        $xml->appendChild($rootNode);
        $sourceNode = $xml->createElement(self::XML_SOURCE_NODE_NAME);
        $sourceNode->appendChild(new DOMCdataSection(self::encodeSource($originalSource)));
        $rootNode->appendChild($sourceNode);

        return $xml->saveXML();
    }

    /**
     * @param array $source
     *
     * @return array
     */
    public static function filterRawSource(array $source)
    {
        return self::prepareSourceArray($source);
    }
    
    /**
     * @inheritdoc
     */
    private static function rowToXMLNode(DOMDocument $document, $name, $value, & $keySettings)
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
        $xpath = new DOMXPath($xml);

        return $xpath;
    }

    public static function xmlDecode($content)
    {
        self::logMessage(vsprintf('Starting XML file decoding : %s', [base64_encode(var_export($content, true))]));
        $xpath = self::prepareXPath($content);

        $stringPath = '/data/string';
        $sourcePath = '/data/source';

        $nodeList = $xpath->query($stringPath);

        $fields = [];

        for ($i = 0; $i < $nodeList->length; $i++) {
            $item = $nodeList->item($i);
            /**
             * @var \DOMNode $item
             */
            $name = $item->getAttribute('name');

            $params = new TranslationStringFilterParameters();
            $params->setDom($item->ownerDocument);
            $params->setNode($item);
            $params->setFilterSettings(self::getFieldProcessingParams());

            $params = apply_filters(ExportedAPI::FILTER_SMARTLING_TRANSLATION_STRING_RECEIVED, $params);

            $nodeValue = $params->getNode()->nodeValue;
            $fields[$name] = $nodeValue;
        }


        $nodeList = $xpath->query($sourcePath);

        $source = self::decodeSource($nodeList->item(0)->nodeValue);

        $flatSource = self::prepareSourceArray($source, 'download');

        foreach ($fields as $key => $value) {
            $flatSource[$key] = $value;
        }

        foreach ($flatSource as & $value) {
            if (is_numeric($value) && is_string($value)) {
                $value += 0;
            }
        }

        $settings = self::getFieldProcessingParams();
        $flatSource = self::removeFields($flatSource, $settings['ignore']);

        return self::structurizeArray($flatSource);;
    }

    public static function hasStringsForTranslation($xml)
    {
        $xpath = self::prepareXPath($xml);

        $stringPath = '/data/string';

        $nodeList = $xpath->query($stringPath);

        return $nodeList->length > 0;
    }
}
