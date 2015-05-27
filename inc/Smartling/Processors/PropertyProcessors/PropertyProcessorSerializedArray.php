<?php

namespace Smartling\Processors\PropertyProcessors;

use DOMAttr;
use DOMDocument;
use DOMNode;
use Smartling\Processors\PropertyDescriptor;

/**
 * Class PropertyProcessorSerializedArray
 *
 * @package Smartling\Processors\PropertyProcessors
 */
class PropertyProcessorSerializedArray extends PropertyProcessorDefault {

	/**
	 * @inheritdoc
	 */
	public function init () {
		$this->setSupportedType( 'serialized-php-array' );
	}

	/**
	 * @inheritdoc
	 */
	public function toXML ( DOMDocument $document, PropertyDescriptor $descriptor, PropertyProcessorFactory $factory ) {
		$node = $document->createElement( PropertyProcessorAbstract::XML_STRUCTURED_NODE_NAME );

		$node->appendChild( new DOMAttr( PropertyProcessorAbstract::XML_NODE_ATTR_IDENITY, $descriptor->getName() ) );
		$node->appendChild( new DOMAttr( PropertyProcessorAbstract::XML_NODE_ATTR_TYPE, $descriptor->getType() ) );

		if ( $descriptor->isMeta() ) {
			$node->appendChild( new DOMAttr( PropertyProcessorAbstract::XML_NODE_ATTR_META, $descriptor->isMeta() ) );
		}

		foreach ( $descriptor->getSubFields() as $subField ) {
			$processor = $factory->getProcessor( $subField->getType() );
			$node->appendChild( $processor->toXML( $document, $subField, $factory ) );
		}

		return $node;
	}

	/**
	 * @inheritdoc
	 */
	function fromXML ( DOMNode $node, PropertyProcessorFactory $factory ) {
		$data = array ();
		if ( $node->hasChildNodes() ) {
			$children = $node->childNodes;
			foreach ( $children as $child ) {
				/**
				 * @var DOMNode $child
				 */
				$type = $child->getAttribute( 'type' );
				$name = $child->getAttribute( 'name' );
				$processor = $factory->getProcessor( $type );
				$data[ $name ] = $processor->fromXML( $child, $factory );
			}
		}
		return serialize( $data );
	}
}