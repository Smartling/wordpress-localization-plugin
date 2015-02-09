<?php

namespace Smartling\Helpers;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Class XmlEncoder
 *
 * Encodes given array into XML string and backward
 *
 * @package Smartling\Processors
 */
class XmlEncoder {

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
		$document->appendChild( $document->createComment( ' smartling.translate_paths = data/string ' ) );
		$document->appendChild( $document->createComment( ' smartling.string_format_paths = html : data/string ' ) );

		return $document;
	}

	/**
	 * @param array       $array
	 * @param DOMDocument $document
	 *
	 * @return DOMElement
	 */
	private static function arrayToXml ( array $array, DOMDocument $document ) {
		$rootNode = $document->createElement( 'data' );
		foreach ( $array as $key => $value ) {
			$translationString = $document->createElement( 'string' );

			$attr        = $document->createAttribute( 'name' );
			$attr->value = $key;
			$translationString->appendChild( $attr );

			$text = $document->createCDATASection( $value );
			$translationString->appendChild( $text );
			$rootNode->appendChild( $translationString );
		}

		return $rootNode;
	}


	/**
	 * @param array $data
	 *
	 * @return string
	 */
	public static function xmlEncode ( array $data ) {
		$xml     = self::setTranslationComments( self::initXml() );
		$content = self::arrayToXml( $data, $xml );
		$xml->appendChild( $content );

		return $xml->saveXML();
	}

	/**
	 * @param array $fields
	 * @param       $content
	 *
	 * @return array
	 */
	public static function xmlDecode ( array $fields, $content ) {
		$xml = self::initXml();

		$xml->load( $content );

		$xpath = new DOMXPath( $xml );

		$output = array();

		foreach ( $fields as $field ) {
			$item = $xpath->query( '//string[@name="' . $field . '"]' )->item( 0 );
			if ( $item ) {
				$output[ $field ] = (string) $item->nodeValue;
			}
		}

		return $output;
	}
}