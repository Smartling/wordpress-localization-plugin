<?php

namespace Smartling\Processors\PropertyProcessors;

use Psr\Log\LoggerInterface;
use Smartling\Exception\SmartlingInvalidFactoryArgumentException;
use Smartling\Processors\SmartlingFactoryAbstract;

/**
 * Class PropertyProcessorFactory
 *
 * @package Smartling\Processors\PropertyMapper
 */
class PropertyProcessorFactory extends SmartlingFactoryAbstract {

	/**
	 * @param LoggerInterface $logger
	 */
	public function __construct ( LoggerInterface $logger ) {
		$this->message = '';
		parent::__construct( $logger );
	}

	public function registerDefaultProcessor ( PropertyProcessorAbstract $processor ) {
		parent::setAllowDefault( true );
		parent::setDefaultHandler( $processor );
	}

	/**
	 * @param PropertyProcessorAbstract $processor
	 * @param bool                      $force
	 *
	 * @internal param $type
	 */
	public function registerProcessor ( PropertyProcessorAbstract $processor, $force = false ) {
		parent::registerHandler( $processor->getSupportedType(), $processor, $force );
	}

	/**
	 * @param $type
	 *
	 * @return PropertyProcessorAbstract
	 * @throws SmartlingInvalidFactoryArgumentException
	 */
	function getProcessor ( $type ) {
		return parent::getHandler( $type );
	}
}