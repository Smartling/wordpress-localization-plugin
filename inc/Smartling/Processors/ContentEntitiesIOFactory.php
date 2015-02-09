<?php

namespace Smartling\Processors;

use Psr\Log\LoggerInterface;
use Smartling\DbAl\WordpressContentEntities\EntityAbstract;
use Smartling\Exception\SmartlingInvalidFactoryArgumentException;

/**
 * Class ContentEntitiesIOFactory
 *
 * @package Smartling\Processors
 */
class ContentEntitiesIOFactory extends ProcessorFactoryAbstract {

	/**
	 * @param LoggerInterface $logger
	 */
	public function __construct ( LoggerInterface $logger ) {
		$this->message = 'Requested entity wrapper for content-type \'%s\' is not registered. Called by: %s';
		parent::__construct( $logger );
	}

	/**
	 * @param                $contentType
	 * @param EntityAbstract $mapper
	 * @param bool           $force
	 */
	public function registerMapper ( $contentType, EntityAbstract $mapper, $force = false ) {
		parent::registerMapper( $contentType, $mapper, $force );
	}

	/**
	 * @param $contentType
	 *
	 * @return EntityAbstract
	 * @throws SmartlingInvalidFactoryArgumentException
	 */
	function getMapper ( $contentType ) {
		return parent::getMapper( $contentType );
	}
}