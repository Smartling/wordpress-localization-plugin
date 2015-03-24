<?php

namespace Smartling\Processors;

use Psr\Log\LoggerInterface;
use Smartling\Exception\SmartlingInvalidFactoryArgumentException;

/**
 * Class ProcessorFactoryAbstract
 *
 * @package Smartling\Processors
 */
abstract class ProcessorFactoryAbstract {

	/**
	 * @var array
	 */
	private $mappers = array ();

	/**
	 * @var string
	 */
	protected $message = '';

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @return LoggerInterface
	 */
	protected function getLogger () {
		return $this->logger;
	}

	/**
	 * @param LoggerInterface $logger
	 */
	public function __construct ( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * @param          $contentType
	 * @param          $mapper
	 * @param bool     $force
	 */
	public function registerMapper ( $contentType, $mapper, $force = false ) {
		if ( ! array_key_exists( $contentType, $this->mappers ) ) {
			$this->mappers[ $contentType ] = $mapper;
		} elseif ( true === $force ) {
			unset ( $this->mappers[ $contentType ] );
			$this->registerMapper( $contentType, $mapper );
		}
	}

	/**
	 * @param $contentType
	 *
	 * @throws SmartlingInvalidFactoryArgumentException
	 */
	public function getMapper ( $contentType ) {
		if ( array_key_exists( $contentType, $this->mappers ) ) {
			return $this->mappers[ $contentType ];
		} else {
			$message = vsprintf( $this->message, array ( $contentType, get_called_class() ) );
			$this->getLogger()->error( $message );
			throw new SmartlingInvalidFactoryArgumentException( $message );
		}
	}

}

