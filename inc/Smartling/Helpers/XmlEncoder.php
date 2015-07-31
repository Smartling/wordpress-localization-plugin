<?php

namespace Smartling\Helpers;

use DOMAttr;
use DOMCdataSection;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Smartling\Bootstrap;
use Smartling\Processors\PropertyDescriptor;
use Smartling\Processors\PropertyProcessors\PropertyProcessorAbstract;
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

	const XML_ROOT_NODE_NAME = 'data';
	const XML_STRING_NODE_NAME = 'string';
	const XML_SOURCE_NODE_NAME = 'source';

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

		$document->appendChild(
			$document->createComment(
				vsprintf(
					' %s ',
					array (
						'Smartling Wordpress Connector v. ' . Bootstrap::getCurrentVersion()
					)
				)
			)
		);

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
	 * @param array  $array
	 * @param string $base
	 * @param string $divider
	 *
	 * @return array
	 */
	protected static function flatternArray ( array $array, $base = '', $divider = '/' ) {
		$output = array ();

		foreach ( $array as $key => $element ) {

			$path = '' === $base ? urlencode( $key ) : implode( $divider, array ( $base, urlencode( $key ) ) );

			if ( is_array( $element ) ) {

				$tmp    = self::flatternArray( $element, $path );
				$output = array_merge( $output, $tmp );
			} else {
				$output[ $path ] = (string) $element;
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
	protected function structurizeArray ( array $flatArray, $divider = '/' ) {
		$output = array ();

		foreach ( $flatArray as $key => $element ) {
			$tempArray    = array ();
			$pathElements = explode( $divider, $key );

			$pointer = &$output;

			for ( $i = 0; $i < ( count( $pathElements ) - 1 ); $i ++ ) {

				if (!isset($pointer[$pathElements[$i]]))
				{
					$pointer[$pathElements[$i]]=array();
				}



				$pointer = &$pointer[ $pathElements[ $i ] ];

			}

			$pointer[ end( $pathElements ) ] = $element;

		}
		return $output;
	}

	private static function normalizeSource ( array $source ) {
		$t = &$source['meta'];

		foreach ( $t as & $value ) {
			if ( is_array($value) && 1 === count( $value ) ) {
				$value = reset( $value );
			}
		}

		return $source;
	}


	private static function optimizeFlatArray ( array $source ) {
		$settings = Bootstrap::getContainer()->getParameter( 'field.processor' );

		$ignoreList = &$settings['ignore'];
		$rebuild    = array ();
		foreach ( $source as $key => $value ) {
			foreach ( $ignoreList as $item ) {

				if ( false !== strpos( $key, urlencode( $item ) ) ) {
					continue 2;
				}
			}
			$rebuild[ $key ] = $value;
		}

		$source = $rebuild;

		$ignoreList = &$settings['copy']['name'];
		$rebuild    = array ();
		foreach ( $source as $key => $value ) {
			foreach ( $ignoreList as $item ) {

				if ( false !== strpos( $key, urlencode( $item ) ) ) {
					continue 2;
				}
			}
			$rebuild[ $key ] = $value;
		}

		$source = $rebuild;


		$ignoreList = &$settings['copy']['regexp'];
		$rebuild    = array ();
		foreach ( $source as $key => $value ) {


			foreach ( $ignoreList as $item ) {

				if ( preg_match( "/{$item}/ius", $value ) ) {
					continue 2;
				}
			}


			$rebuild[ $key ] = $value;
		}

		$source = $rebuild;

		$rebuild = array ();
		foreach ( $source as $key => $value ) {

			if ( empty( $value ) ) {
				continue;
			}

			$rebuild[ $key ] = $value;
		}

		$source = $rebuild;

		return $source;


	}

	/**
	 * @param array $source
	 *
	 * @return string
	 */
	public static function xmlEncode ( array $source ) {

		$originalSource = $source;

		$source = self::normalizeSource( $source );

		foreach ( $source as & $value ) {
			if ( false !== ( $tmp = @unserialize( $value ) ) ) {
				$value = $tmp;
			}
		}

		foreach ( $source['meta'] as & $value ) {
			if ( false !== ( $tmp = @unserialize( $value ) ) ) {
				$value = $tmp;
			}
		}

		$source = self::flatternArray( $source );
		$source = self::optimizeFlatArray( $source );

		$xml = self::setTranslationComments( self::initXml() );

		$settings = Bootstrap::getContainer()->getParameter( 'field.processor' );

		$ignoreList = &$settings['key'];

		$rnode = $xml->createElement( self::XML_ROOT_NODE_NAME );

		foreach ( $source as $name => $value ) {
			$rnode->appendChild( self::rowToXMLNode( $xml, $name, $value, $ignoreList ) );
		}

		$xml->appendChild( $rnode );

		$node = $xml->createElement( self::XML_SOURCE_NODE_NAME );
		$node->appendChild( new DOMCdataSection( implode( "\n",
			str_split( base64_encode( serialize( $originalSource ) ), 80 ) ) ) );

		$rnode->appendChild( $node );

		return $xml->saveXML();
	}

	/**
	 * @inheritdoc
	 */
	private static function rowToXMLNode ( DOMDocument $document, $name, $value, & $keySettings ) {
		$node = $document->createElement( self::XML_STRING_NODE_NAME );
		$node->appendChild( new DOMAttr( 'name', $name ) );
		foreach ( $keySettings as $key => $fields ) {
			foreach ( $fields as $field ) {
				if ( false !== strpos( $name, $field ) ) {
					$node->appendChild( new DOMAttr( 'key', $key ) );
				}
			}
		}
		$node->appendChild( new DOMCdataSection( $value ) );

		return $node;
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

	public static function xmlDecode ( $content ) {
		$xpath = self::prepareXPath( $content );

		$stringPath = '/data/string';
		$sourcePath = '/data/source';

		$nodeList = $xpath->query( $stringPath );

		$source = '';
		$fields = array ();

		for ( $i = 0; $i < $nodeList->length; $i ++ ) {
			$item            = $nodeList->item( $i );
			$name            = $item->getAttribute( 'name' );
			$value           = $item->nodeValue;
			$fields[ $name ] = $value;
		}

		$nodeList = $xpath->query( $sourcePath );
		$source   = unserialize( base64_decode( $nodeList->item( 0 )->nodeValue ) );

		$source = self::normalizeSource( $source );

		foreach ( $source as & $value ) {
			if ( false !== ( $tmp = @unserialize( $value ) ) ) {
				$value = $tmp;
			}
		}

		foreach ( $source['meta'] as & $value ) {
			if ( false !== ( $tmp = @unserialize( $value ) ) ) {
				$value = $tmp;
			}
		}


		$flatSource = self::flatternArray( $source );

		foreach ( $fields as $key => $value ) {
			$flatSource[ $key ] = $value;
		}

		foreach ($flatSource as & $value)
		{
			if (is_numeric($value) && is_string($value))
			{
				$value +=0;
			}
		}


		$settings = Bootstrap::getContainer()->getParameter( 'field.processor' );

		$ignoreList = &$settings['ignore'];
		$rebuild    = array ();
		foreach ( $flatSource as $key => $value ) {
			foreach ( $ignoreList as $item ) {

				if ( false !== strpos( $key, urlencode( $item ) ) ) {
					continue 2;
				}
			}
			$rebuild[ $key ] = $value;
		}

		$flatSource = $rebuild;

		$flatSource = self::structurizeArray( $flatSource );

		return $flatSource;
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