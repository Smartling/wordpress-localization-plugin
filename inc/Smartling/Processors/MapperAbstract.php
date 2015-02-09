<?php

namespace Smartling\Processors;

/**
 * Class MapperAbstract
 *
 * @package Smartling\Processors
 */
abstract class MapperAbstract {

	/**
	 * @var array
	 */
	private $fields;

	/**
	 * return array
	 */
	public function getFields () {
		return $this->fields;
	}

	/**
	 * @param array
	 */
	protected function setFields ( $fields ) {
		$this->fields = $fields;
	}
}