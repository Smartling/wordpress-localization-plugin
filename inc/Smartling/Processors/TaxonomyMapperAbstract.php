<?php

namespace Smartling\Processors;

/**
 * Class TaxonomyMapperAbstract
 *
 * @package Smartling\Processors
 */
abstract class TaxonomyMapperAbstract extends MapperAbstract {

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