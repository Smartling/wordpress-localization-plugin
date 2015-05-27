<?php

namespace Smartling\Helpers;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Smartling\Processors\PropertyDescriptor;
use Smartling\Processors\PropertyProcessors\PropertyProcessorFactory;

/**
 * Class XmlEncoder
 *
 * Encodes given array into XML string and backward
 *
 * @package Smartling\Processors
 */
class XmlEncoder {

	private static $magicComments = array (
		'smartling.translate_paths = data/string, data/structure/string',
		'smartling.string_format_paths = html : data/string, html: data/structure/string',
		'smartling.source_key_paths = data/{string.key}',
		'smartling.variants_enabled = true',
	);

	/**
	 * @return DOMDocument
	 */
	private static function initXml () {
		$xml = new DOMDocument( '1.0', 'UTF-8' );

		return $xml;
	}

	/**
	 * Sets comments about translation type (html)
	 *
	 * @param DOMDocument $document
	 *
	 * @return DOMDocument
	 */
	private static function setTranslationComments ( DOMDocument $document ) {
		foreach ( self::$magicComments as $commentString ) {
			$document->appendChild( $document->createComment( vsprintf( ' %s ', array ( $commentString ) ) ) );
		}

		return $document;
	}

	/**
	 * @param PropertyDescriptor[]     $array
	 * @param DOMDocument              $document
	 * @param PropertyProcessorFactory $factory
	 *
	 * @return DOMElement
	 */
	private static function arrayToXml ( array $array, DOMDocument $document, PropertyProcessorFactory $factory ) {
		$rootNode = $document->createElement( 'data' );
		foreach ( $array as $descriptor ) {
			$processor = $factory->getProcessor( $descriptor->getType() );
			$node      = $processor->toXML( $document, $descriptor, $factory );
			$rootNode->appendChild( $node );
		}

		return $rootNode;
	}


	/**
	 * @param PropertyDescriptor[]     $data
	 * @param PropertyProcessorFactory $factory
	 *
	 * @return string
	 */
	public static function xmlEncode ( array $data, PropertyProcessorFactory $factory ) {
		$xml     = self::setTranslationComments( self::initXml() );
		$content = self::arrayToXml( $data, $xml, $factory );
		$xml->appendChild( $content );

		return $xml->saveXML();
	}

	/**
	 * @param string $xmlString
	 *
	 * @return DOMXPath
	 */
	private static function prepareXPath ( $xmlString ) {
		$xml = self::initXml();
		$xml->loadXML( $xmlString );
		$xpath = new DOMXPath( $xml );

		return $xpath;
	}

	/**
	 * @param PropertyDescriptor[]     $descriptors
	 * @param                          $content
	 * @param PropertyProcessorFactory $factory
	 *
	 * @return \Smartling\Processors\PropertyDescriptor[]
	 */
	public static function xmlDecode ( array $descriptors, $content, PropertyProcessorFactory $factory ) {
		$xpath = self::prepareXPath( $content );

		foreach ( $descriptors as $descriptor ) {
			$path     = self::buildXPath( $descriptor );
			$nodeList = $xpath->query( $path );
			$item     = $nodeList->item( 0 );
			if ( $item ) {
				$descriptor->setValue( $factory->getProcessor( $descriptor->getType() )->fromXML( $item, $factory ) );
			}
		}

		return $descriptors;
	}

	private static function buildXPath ( PropertyDescriptor $descriptor ) {
		$str = '';
		switch ( $descriptor->getType() ) {
			case 'serialized-php-array': {
				$str = '/data/structure[@name="%s"]';
				break;
			}
			default: {
				$str = '/data/string[@name="%s"]';
				break;
			}
		}

		return vsprintf( $str, array ( $descriptor->getName() ) );
	}
}