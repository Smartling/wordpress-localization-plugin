<?php

namespace Smartling\Processors;

/**
 * Class TagMapper
 *
 * @package Smartling\Processors
 */
class TagMapper extends MapperAbstract {

	/**
	 * Constructor
	 */
	function __construct () {
		$this->setFields(
			array (
				'name',
				'description'
			)
		);
	}
}