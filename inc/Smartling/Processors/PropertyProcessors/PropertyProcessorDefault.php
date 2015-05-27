<?php

namespace Smartling\Processors\PropertyProcessors;

use DOMAttr;
use DOMCdataSection;
use DOMDocument;
use DOMNode;
use Smartling\Processors\PropertyDescriptor;

/**
 * Class PropertyProcessorDefault
 *
 * @package Smartling\Processors\PropertyProcessors
 */
class PropertyProcessorDefault extends PropertyProcessorAbstract {

	/**
	 * @inheritdoc
	 */
	public function toXML ( DOMDocument $document, PropertyDescriptor $descriptor, PropertyProcessorFactory $factory ) {
		$node = $document->createElement( PropertyProcessorAbstract::XML_NODE_NAME );

		$node->appendChild( new DOMAttr( PropertyProcessorAbstract::XML_NODE_ATTR_IDENITY, $descriptor->getName() ) );
		$node->appendChild( new DOMAttr( PropertyProcessorAbstract::XML_NODE_ATTR_TYPE, $descriptor->getType() ) );

		if ( $descriptor->isMeta() ) {
			$node->appendChild( new DOMAttr( PropertyProcessorAbstract::XML_NODE_ATTR_META, $descriptor->isMeta() ) );
		}

		$node->appendChild( new DOMCdataSection( $descriptor->getValue() ) );

		return $node;
	}

	/**
	 * @inheritdoc
	 */
	function fromXML ( DOMNode $node, PropertyProcessorFactory $factory  ) {
		return $node->nodeValue;
	}
}