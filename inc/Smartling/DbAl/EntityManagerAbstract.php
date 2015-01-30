<?php

namespace Smartling\DbAl;

use Psr\Log\LoggerInterface;

abstract class EntityManagerAbstract {

	/**
	 * @var LoggerInterface
	 */
	protected $logger;

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