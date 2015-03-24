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
		'smartling.translate_paths = data/string',
		'smartling.string_format_paths = html : data/string',
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
			$node =
				$factory->getProcessor(
					$descriptor->getType()
				)
				        ->toXML( $document, $descriptor );
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
	 * @param PropertyDescriptor[] $descriptors
	 * @param                      $content
	 *
	 * @return PropertyDescriptor[]
	 */
	public static function xmlDecode ( array $descriptors, $content, PropertyProcessorFactory $factory ) {
		$xml = self::initXml();
		$xml->loadXML( $content );
		$xpath = new DOMXPath( $xml );

		foreach ( $descriptors as $descriptor ) {
			$path     = self::buildXPath( $descriptor );
			$nodeList = $xpath->query( $path );
			$item     = $nodeList->item( 0 );
			if ( $item ) {
				$descriptor->setValue( $factory->getProcessor( $descriptor->getType() )->fromXML( $item ) );
			}
		}

		return $descriptors;
	}

	private static function buildXPath ( PropertyDescriptor $descriptor ) {
		return vsprintf( '//string[@name="%s"]', array ( $descriptor->getName() ) );
	}
}