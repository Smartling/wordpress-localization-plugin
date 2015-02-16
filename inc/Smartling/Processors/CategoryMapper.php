<?php

namespace Smartling\Processors;

/**
 * Class PageMapper
 *
 * @package Smartling\Processors
 */
class CategoryMapper extends MapperAbstract {

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