<?php

namespace Smartling\DbAl;

use Psr\Log\LoggerInterface;

/**
 * Class EntityManagerAbstract
 *
 * @package Smartling\DbAl
 */
abstract class EntityManagerAbstract {

	/**
	 * @var LoggerInterface
	 */
	protected $logger;

	/**
	 * @return LoggerInterface
	 */
	public function getLogger () {
		return $this->logger;
	}

	/**
	 * @var SmartlingToCMSDatabaseAccessWrapperInterface
	 */
	protected $dbal;

	/**
	 * Constructor
	 *
	 * @param LoggerInterface                              $logger
	 * @param SmartlingToCMSDatabaseAccessWrapperInterface $dbal
	 */
	public function __construct ( LoggerInterface $logger, SmartlingToCMSDatabaseAccessWrapperInterface $dbal ) {
		$this->logger = $logger;
		$this->dbal   = $dbal;
	}


}