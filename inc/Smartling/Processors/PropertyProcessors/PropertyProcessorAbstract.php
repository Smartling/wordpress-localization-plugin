<?php

namespace Smartling\Processors\PropertyProcessors;

use DOMDocument;
use DOMElement;
use DOMNode;
use Psr\Log\LoggerInterface;
use Smartling\Processors\PropertyDescriptor;

abstract class PropertyProcessorAbstract {

	const XML_NODE_NAME = 'string';

	/**
	 * Content-type wide property name
	 */
	const XML_NODE_ATTR_IDENITY = 'name';

	/**
	 * 'key' attribute value. Ignored if empty.
	 */
	const XML_NODE_ATTR_KEY = 'key';

	/**
	 * Determines if text is from metadata. Ignored if false
	 */
	const XML_NODE_ATTR_META = 'meta';

	/**
	 * Unique property identity
	 */
	const XML_NODE_ATTR_TYPE = 'type';

	/**
	 * @var string
	 */
	private $supportedType = '';

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @return LoggerInterface
	 */
	public function getLogger () {
		return $this->logger;
	}

	/**
	 * @return string
	 */
	public function getSupportedType () {
		return $this->supportedType;
	}

	/**
	 * @param string $supportedType
	 */
	public function setSupportedType ( $supportedType ) {
		$this->supportedType = $supportedType;
	}


	public function __construct ( LoggerInterface $logger ) {
		$this->logger = $logger;
		$this->init();
	}

	protected function init () {
	}

	/**
	 * @param DOMDocument        $document
	 * @param PropertyDescriptor $descriptor
	 *
	 * @return DOMElement
	 */
	abstract function toXML ( DOMDocument $document, PropertyDescriptor $descriptor );

	/**
	 * @param DOMNode $node
	 *
	 * @return mixed
	 */
	abstract function fromXML ( DOMNode $node );
}