<?php

namespace Smartling\Helpers;

use DOMAttr;
use DOMCdataSection;
use DOMDocument;
use DOMXPath;
use Psr\Log\LoggerInterface;
use Smartling\Bootstrap;
use Smartling\Exception\InvalidXMLException;

/**
 * Class XmlEncoder
 *
 * Encodes given array into XML string and backward
 *
 * @package Smartling\Processors
 */
class XmlEncoder {

	/**
	 * Is raised just before encoding to XML
	 * attributes:
	 *  & array Fields from entity and its metadata as they are (may be serialized / combined / encoded )
	 *  SubmissionEntity instance of SubmissionEntity
	 *  EntityAbstract successor instance (Original Entity)
	 *  Original Entity Metadata array
	 *
	 *
	 *  Note! The only prepared array which is going to be serialized into XML is to be received by reference.
	 *  You should not change / add / remove array keys.
	 *  Only update of values is allowed.
	 *  Will be changed to ArrayAccess implementation.
	 */
	const EVENT_SMARTLING_BEFORE_SERIALIZE_CONNENT = 'EVENT_SMARTLING_BEFORE_SERIALIZE_CONNENT';

	/**
	 * Is raised just after decoding from XML
	 * attributes:
	 *  & array of translated fields
	 *  SubmissionEntity instance of SubmissionEntity
	 *  EntityAbstract successor instance (Target Entity)
	 *  Target Entity Metadata array
	 *
	 *
	 *
	 *  Note! The only translation fields array is to be received by reference.
	 *  You should not change / add / remove array keys.
	 *  Only update of values is allowed.
	 *  Will be changed to ArrayAccess implementation.
	 */
	const EVENT_SMARTLING_AFTER_DESERIALIZE_CONTENT = 'EVENT_SMARTLING_AFTER_DESERIALIZE_CONTENT';

	/**
	 * Logs XML related message.
	 * Controlled by logger.smartling_verbose_output_for_xml_coding bool value
	 *
	 * @param $message
	 */
	public static function logMessage ( $message ) {
		if ( true === (bool) Bootstrap::getContainer()->getParameter( 'logger.smartling_verbose_output_for_xml_coding' ) ) {
			Bootstrap::getLogger()->debug( $message );
		}
	}

	private static $magicComments = [
		'smartling.translate_paths = data/string',
		'smartling.string_format_paths = html : data/string',
		'smartling.source_key_paths = data/{string.key}',
		'smartling.variants_enabled = true',
	];

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
			$document->appendChild( $document->createComment( vsprintf( ' %s ', [ $commentString ] ) ) );
		}

		$additionalComments = [
			'Smartling Wordpress Connector version: ' . Bootstrap::getCurrentVersion(),
			'Wordpress installation host: ' . Bootstrap::getHttpHostName(),
		];

		foreach ( $additionalComments as $extraComment ) {
			$document->appendChild( $document->createComment( vsprintf( ' %s ', [ $extraComment ] ) ) );
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
	protected static function flatternArray ( array $array, $base = '', $divider = '/' ) {
		$output = [ ];

		foreach ( $array as $key => $element ) {

			$path = '' === $base ? $key : implode( $divider, [ $base, $key ] );

			$valueType = gettype( $element );

			switch ( $valueType ) {
				case 'array': {
					$tmp    = self::flatternArray( $element, $path );
					$output = array_merge( $output, $tmp );
					break;
				}
				case 'NULL':
				case 'boolean':
				case 'string':
				case 'integer':
				case 'double': {
					$output[ $path ] = (string) $element;
					break;
				}
				case 'unknown type':
				case 'resource':
				case 'object': {
					$message = vsprintf(
						'Unsupported type \'%s\' found in scope for translation. Skipped. Contents: \'%s\'',
						[
							$valueType,
							var_export( $element, true ),
						]
					);
					self::logMessage( $message );
				}

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
	protected static function structurizeArray ( array $flatArray, $divider = '/' ) {
		$output = [ ];

		foreach ( $flatArray as $key => $element ) {
			$pathElements = explode( $divider, $key );
			$pointer      = &$output;
			for ( $i = 0; $i < ( count( $pathElements ) - 1 ); $i ++ ) {
				if ( ! isset( $pointer[ $pathElements[ $i ] ] ) ) {
					$pointer[ $pathElements[ $i ] ] = [ ];
				}
				$pointer = &$pointer[ $pathElements[ $i ] ];
			}
			$pointer[ end( $pathElements ) ] = $element;
		}

		return $output;
	}

	/**
	 * @param array $source
	 *
	 * @return array
	 */
	private static function normalizeSource ( array $source ) {
		if ( array_key_exists( 'meta', $source ) && is_array( $source['meta'] ) ) {
			$pointer = &$source['meta'];
			foreach ( $pointer as & $value ) {
				if ( is_array( $value ) && 1 === count( $value ) ) {
					$value = reset( $value );
				}
			}
		}

		return $source;
	}

	/**
	 * @return mixed
	 */
	private static function getFieldProcessingParams () {
		return Bootstrap::getContainer()->getParameter( 'field.processor' );
	}

	private static function removeFields ( $array, $list ) {
		$rebuild = [ ];
		$pattern = '#(' . implode( '|', $list ) . ')$#us';
		foreach ( $array as $key => $value ) {
			if ( 1 === preg_match( $pattern, $key ) ) {
				$debugMessage = vsprintf( 'Field \'%s\' removed', [ $key ] );
				self::logMessage( $debugMessage );
				continue;
			} else {
				$rebuild[ $key ] = $value;
			}
		}

		return $rebuild;
	}

	/**
	 * @param $array
	 *
	 * @return array
	 */
	private static function removeEmptyFields ( $array ) {
		$rebuild = [ ];
		foreach ( $array as $key => $value ) {
			if ( empty( $value ) ) {
				$debugMessage = vsprintf( 'Removed empty field \'%s\'', [ $key ] );
				self::logMessage( $debugMessage );
				continue;
			}
			$rebuild[ $key ] = $value;
		}

		return $rebuild;
	}

	/**
	 * @param $array
	 * @param $list
	 *
	 * @return array
	 */
	private static function removeValuesByRegExp ( $array, $list ) {
		$rebuild = [ ];
		foreach ( $array as $key => $value ) {
			foreach ( $list as $item ) {
				if ( preg_match( "/{$item}/us", $value ) ) {
					$debugMessage = vsprintf( 'Removed field by value: filedName:\'%s\' fieldValue:\'%s\' filter:\'%s\'',
						[ $key, $value, $item ] );
					self::logMessage( $debugMessage );
					continue 2;
				}
			}
			$rebuild[ $key ] = $value;
		}

		return $rebuild;
	}

	private static function prepareSourceArray ( $sourceArray, $strategy = 'send' ) {
		$sourceArray = self::normalizeSource( $sourceArray );

		/*foreach ( $sourceArray as & $value ) {
			if ( false !== ( $tmp = @unserialize( $value ) ) ) {
				$value = $tmp;
			}
		}*/

		if ( array_key_exists( 'meta', $sourceArray ) && is_array( $sourceArray['meta'] ) ) {
			foreach ( $sourceArray['meta'] as & $value ) {
				if ( is_array( $value ) && array_key_exists( 'entity', $value ) && array_key_exists( 'meta',
						$value )
				) {
					// nested object detected
					$value = self::prepareSourceArray( $value, $strategy );
				}

				$value = maybe_unserialize( $value );
			}
		}
		$sourceArray = self::flatternArray( $sourceArray );

		$settings = self::getFieldProcessingParams();

		if ( 'send' === $strategy ) {
			$sourceArray = self::removeFields( $sourceArray, $settings['ignore'] );
			$sourceArray = self::removeFields( $sourceArray, $settings['copy']['name'] );
			$sourceArray = self::removeValuesByRegExp( $sourceArray, $settings['copy']['regexp'] );
			$sourceArray = self::removeEmptyFields( $sourceArray );
		}

		return $sourceArray;

	}

	private static function is_serialized ( $value, &$result = null ) {
		if ( ! is_string( $value ) ) {
			return false;
		}

		if ( $value === 'b:0;' ) {
			$result = false;

			return true;
		}
		$length = strlen( $value );
		$end    = '';
		switch ( @$value[0] ) {
			case 's':
				if ( $value[ $length - 2 ] !== '"' ) {
					return false;
				}
			case 'b':
			case 'i':
			case 'd':
				// This looks odd but it is quicker than isset()ing
				$end .= ';';
			case 'a':
			case 'O':
				$end .= '}';
				if ( $value[1] !== ':' ) {
					return false;
				}
				switch ( $value[2] ) {
					case 0:
					case 1:
					case 2:
					case 3:
					case 4:
					case 5:
					case 6:
					case 7:
					case 8:
					case 9:
						break;
					default:
						return false;
				}
			case 'N':
				$end .= ';';
				if ( $value[ $length - 1 ] !== $end[0] ) {
					return false;
				}
				break;
			default:
				return false;
		}
		if ( ( $result = @unserialize( $value ) ) === false ) {
			$result = null;

			return false;
		}

		return true;
	}

	private static function encodeSource ( $source, $stringLength = 120 ) {
		return "\n" . implode( "\n", str_split( base64_encode( serialize( $source ) ), $stringLength ) );
	}

	private static function decodeSource ( $source ) {
		return unserialize( base64_decode( $source ) );
	}

	/**
	 * @param array $source
	 *
	 * @return string
	 */
	public static function xmlEncode ( array $source ) {
		$originalSource = $source;
		$source         = self::prepareSourceArray( $source );
		$xml            = self::setTranslationComments( self::initXml() );
		$settings       = self::getFieldProcessingParams();
		$keySettings    = &$settings['key'];
		$rootNode       = $xml->createElement( self::XML_ROOT_NODE_NAME );
		foreach ( $source as $name => $value ) {
			$rootNode->appendChild( self::rowToXMLNode( $xml, $name, $value, $keySettings ) );
		}
		$xml->appendChild( $rootNode );
		$sourceNode = $xml->createElement( self::XML_SOURCE_NODE_NAME );
		$sourceNode->appendChild( new DOMCdataSection( self::encodeSource( $originalSource ) ) );
		$rootNode->appendChild( $sourceNode );

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
	 * @throws InvalidXMLException
	 */
	private static function prepareXPath ( $xmlString ) {
		$xml    = self::initXml();
		$result = @$xml->loadXML( $xmlString );
		if ( false === $result ) {
			throw new InvalidXMLException( 'Invalid XML Contents' );
		}
		$xpath = new DOMXPath( $xml );

		return $xpath;
	}

	public static function xmlDecode ( $content ) {
		self::logMessage( vsprintf( 'Decoding XML file: %s', [ $content ] ) );
		$xpath = self::prepareXPath( $content );

		$stringPath = '/data/string';
		$sourcePath = '/data/source';

		$nodeList = $xpath->query( $stringPath );

		$fields = [ ];

		for ( $i = 0; $i < $nodeList->length; $i ++ ) {
			$item            = $nodeList->item( $i );
			$name            = $item->getAttribute( 'name' );
			$value           = $item->nodeValue;
			$fields[ $name ] = $value;
		}

		$nodeList = $xpath->query( $sourcePath );

		$source = self::decodeSource( $nodeList->item( 0 )->nodeValue );

		$flatSource = self::prepareSourceArray( $source, 'download' );

		foreach ( $fields as $key => $value ) {
			$flatSource[ $key ] = $value;
		}

		foreach ( $flatSource as & $value ) {
			if ( is_numeric( $value ) && is_string( $value ) ) {
				$value += 0;
			}
		}

		$settings   = self::getFieldProcessingParams();
		$flatSource = self::removeFields( $flatSource, $settings['ignore'] );

		return self::structurizeArray( $flatSource );;
	}

	public static function hasStringsForTranslation ( $xml ) {
		$xpath = self::prepareXPath( $xml );

		$stringPath = '/data/string';

		$nodeList = $xpath->query( $stringPath );

		return $nodeList->length > 0;
	}
}