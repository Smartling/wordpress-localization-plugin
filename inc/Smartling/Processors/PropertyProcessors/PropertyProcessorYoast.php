<?php

namespace Smartling\Processors\PropertyProcessors;

use DOMDocument;
use Smartling\Processors\PropertyDescriptor;

/**
 * Class PropertyProcessorYoast
 *
 * @package Smartling\Processors\PropertyProcessors
 */
class PropertyProcessorYoast extends PropertyProcessorDefault {

	public function init () {
		$this->setSupportedType( 'wordpress-seo' );
	}

	/**
	 * @inheritdoc
	 */
	public function toXML ( DOMDocument $document, PropertyDescriptor $descriptor, PropertyProcessorFactory $factory ) {
		$node = parent::toXML( $document, $descriptor,$factory );
		if ( '' !== $descriptor->getKey() ) {
			$node->appendChild( new \DOMAttr( PropertyProcessorAbstract::XML_NODE_ATTR_KEY, $descriptor->getKey() ) );
		}

		return $node;
	}

}