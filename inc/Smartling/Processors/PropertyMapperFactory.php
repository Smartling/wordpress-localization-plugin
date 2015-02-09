<?php

namespace Smartling\Processors;

use Psr\Log\LoggerInterface;
use Smartling\Exception\SmartlingInvalidFactoryArgumentException;


/**
 * Class PropertyMapperFactory
 *
 * @package Smartling\Processors
 */
class PropertyMapperFactory extends ProcessorFactoryAbstract {

	/**
	 * @param LoggerInterface $logger
	 */
	public function __construct ( LoggerInterface $logger ) {
		$this->message = 'Requested mapper for content-type \'%s\' is not registered. Called by: %s';
		parent::__construct( $logger );
	}

	/**
	 * @param                $contentType
	 * @param                $mapper
	 * @param bool           $force
	 */
	public function registerMapper ( $contentType, $mapper, $force = false ) {
		parent::registerMapper( $contentType, $mapper, $force );
	}

	/**
	 * @param $contentType
	 *
	 * @return MapperAbstract
	 * @throws SmartlingInvalidFactoryArgumentException
	 */
	function getMapper ( $contentType ) {
		return parent::getMapper( $contentType );
	}

}