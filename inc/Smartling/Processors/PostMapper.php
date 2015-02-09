<?php

namespace Smartling\Processors;

/**
 * Class PostMapper
 *
 * @package Smartling\Processors
 */
class PostMapper extends MapperAbstract {

	/**
	 * Constructor
	 */
	function __construct () {
		$this->setFields(
			array (
				'post_title',
				'post_content'
			)
		);
	}
}